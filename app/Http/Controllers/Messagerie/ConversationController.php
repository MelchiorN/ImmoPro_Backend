<?php

namespace App\Http\Controllers\Messagerie;

use App\Events\MessageDelivreEvent;
use App\Events\NouveauMessageEvent;
use App\Http\Controllers\Controller;
use App\Mail\NouveauMessageMail;
use App\Models\Bien;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageSuppression;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConversationController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/messagerie/conversations
    // Liste les conversations de l'utilisateur connecté (agent ou client)
    // Triées par dernier message décroissant (style WhatsApp)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::with([
            'agent:id,first_name,last_name,profile_picture',
            'client:id,first_name,last_name,profile_picture',
            'bien:id,titre,type_bien',
            'dernierMessage',
        ])
        ->where(function ($q) use ($user) {
            $q->where('agent_id', $user->id)
              ->orWhere('client_id', $user->id);
        })
        ->orderByDesc('dernier_message_le')
        ->get()
        ->map(fn ($conv) => $this->formatConversation($conv, $user));

        return response()->json([
            'success' => true,
            'data'    => $conversations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/messagerie/conversations
    // Ouvrir ou récupérer une conversation existante
    // Body: { agent_id, client_id, bien_id? }
    // Seul un agent ou un client peut initier (les deux parties doivent correspondre à l'utilisateur)
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'agent_id'  => 'required|uuid|exists:users,id',
            'client_id' => 'required|uuid|exists:users,id',
            'bien_id'   => 'nullable|uuid|exists:biens,id',
        ]);

        // S'assurer que l'utilisateur courant est l'un des deux participants
        if ($user->id !== $request->agent_id && $user->id !== $request->client_id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas créer une conversation entre deux autres utilisateurs.',
            ], 403);
        }

        // Vérifier les rôles
        $agent  = \App\Models\User::where('id', $request->agent_id)->where('role', 'agent')->first();
        $client = \App\Models\User::where('id', $request->client_id)->whereIn('role', ['client'])->first();

        if (! $agent || ! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Les participants doivent être un agent et un client.',
            ], 422);
        }

        // Récupérer ou créer — la clé unique est (agent_id, client_id) uniquement.
        // Une seule conversation par paire agent/client, quel que soit le bien contacté.
        // bien_id est enregistré seulement à la création (bien du premier contact).
        $conversation = Conversation::firstOrCreate(
            [
                'agent_id'  => $request->agent_id,
                'client_id' => $request->client_id,
            ],
            [
                'bien_id'            => $request->bien_id, // stocké pour info, ne discrimine plus
                'dernier_message_le' => null,
            ]
        );

        $conversation->load([
            'agent:id,first_name,last_name,profile_picture',
            'client:id,first_name,last_name,profile_picture',
            'bien:id,titre,type_bien',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatConversation($conversation, $user),
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/messagerie/conversations/{id}/messages
    // Charger les messages d'une conversation avec pagination
    // Les messages supprimés (pour l'utilisateur ou pour tous) sont exclus
    // ─────────────────────────────────────────────────────────────────────────

    public function messages(Request $request, string $id): JsonResponse
    {
        $user         = $request->user();
        $conversation = Conversation::findOrFail($id);

        // Vérifier que l'utilisateur est participant
        $this->autoriserParticipant($user, $conversation);

        $messages = $conversation->messages()
            // Exclure les messages supprimés pour cet utilisateur (ou pour tous)
            ->whereNotExists(function ($q) use ($user) {
                $q->select('id')
                  ->from('message_suppressions')
                  ->whereColumn('message_suppressions.message_id', 'messages.id')
                  ->where(function ($q2) use ($user) {
                      $q2->where('user_id', $user->id)
                         ->orWhere('pour_tous', true);
                  });
            })
            ->with('sender:id,first_name,last_name,profile_picture')
            ->orderBy('created_at', 'asc')
            ->paginate($request->integer('per_page', 50));

        // Marquer les messages reçus comme lus
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('lu_le')
            ->update(['lu_le' => now()]);

        return response()->json([
            'success' => true,
            'data'    => $messages->map(fn ($m) => $this->formatMessage($m, $user)),
            'meta'    => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/messagerie/conversations/{id}/messages
    // Envoyer un message dans une conversation
    // Body: { contenu }
    // ─────────────────────────────────────────────────────────────────────────

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $user         = $request->user();
        $conversation = Conversation::findOrFail($id);

        $this->autoriserParticipant($user, $conversation);

        $request->validate([
            'contenu' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();
        try {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'contenu'         => $request->input('contenu'),
            ]);

            // Mettre à jour le timestamp de dernier message (pour le tri des conversations)
            $conversation->update(['dernier_message_le' => now()]);

            DB::commit();

            $message->load('sender:id,first_name,last_name,profile_picture');

            // ── Notifier le destinataire (broadcast + in-app + email) ──────────
            $this->notifierDestinataire($conversation, $message, $user);

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé.',
                'data'    => $this->formatMessage($message, $user),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/messagerie/messages/{messageId}/delivre
    // ACK de délivrance : le destinataire confirme avoir reçu le message
    // en temps réel. Met à jour delivre_le et notifie l'expéditeur.
    // ─────────────────────────────────────────────────────────────────────────

    public function marquerDelivre(Request $request, string $messageId): JsonResponse
    {
        $user    = $request->user();
        $message = Message::with('conversation')->findOrFail($messageId);

        // Seul le destinataire (pas l'expéditeur) peut confirmer la délivrance
        $this->autoriserParticipant($user, $message->conversation);

        if ($message->sender_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'L\'expéditeur ne peut pas confirmer la délivrance de son propre message.'], 422);
        }

        // Idempotent : ne pas écraser si déjà délivré
        if ($message->delivre_le === null) {
            $message->update(['delivre_le' => now()]);
        }

        // Notifier l'expéditeur en temps réel (✔✔ gris)
        try {
            broadcast(new MessageDelivreEvent(
                messageId:      $message->id,
                conversationId: $message->conversation_id,
                expediteurId:   $message->sender_id,
                delivreLe:      $message->delivre_le->toIso8601String(),
            ));
        } catch (\Throwable $e) {
            Log::warning("[Messagerie] Broadcast MessageDelivreEvent échoué : {$e->getMessage()}");
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/messagerie/messages/{messageId}
    // Supprimer un message (pour moi ou pour tous)
    // Body: { pour_tous: true/false }
    // Le message n'est JAMAIS supprimé de la BDD — admin peut toujours le voir
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteMessage(Request $request, string $messageId): JsonResponse
    {
        $user    = $request->user();
        $message = Message::with('conversation')->findOrFail($messageId);

        // Vérifier que l'utilisateur est participant à la conversation
        $this->autoriserParticipant($user, $message->conversation);

        $request->validate([
            'pour_tous' => 'required|boolean',
        ]);

        $pourTous = $request->boolean('pour_tous');

        // Si "pour tous" — vérifier que c'est bien l'expéditeur
        if ($pourTous && $message->sender_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez supprimer pour tous que vos propres messages.',
            ], 403);
        }

        // Vérifier si une suppression existe déjà
        $existing = MessageSuppression::where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            // Si on demande "pour tous" et que c'était "pour moi" → mettre à jour
            if ($pourTous && ! $existing->pour_tous) {
                $existing->update(['pour_tous' => true]);
            }
        } else {
            MessageSuppression::create([
                'message_id'  => $messageId,
                'user_id'     => $user->id,
                'pour_tous'   => $pourTous,
                'supprime_le' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $pourTous
                ? 'Message supprimé pour tout le monde.'
                : 'Message supprimé pour vous.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/messagerie/conversations
    // Vue admin : liste toutes les conversations avec statistiques
    // ─────────────────────────────────────────────────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        $conversations = Conversation::with([
            'agent:id,first_name,last_name,profile_picture,role',
            'client:id,first_name,last_name,profile_picture,role',
            'bien:id,titre,type_bien',
            'dernierMessage',
        ])
        ->withCount('messages')
        ->orderByDesc('dernier_message_le')
        ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $conversations->map(fn ($conv) => $this->formatConversationAdmin($conv)),
            'meta'    => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'total'        => $conversations->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/messagerie/conversations/{id}/messages
    // Vue admin : tous les messages d'une conversation, SANS filtre de suppression
    // L'admin voit tout, y compris les messages supprimés
    // ─────────────────────────────────────────────────────────────────────────

    public function adminMessages(Request $request, string $id): JsonResponse
    {
        $conversation = Conversation::with([
            'agent:id,first_name,last_name,profile_picture',
            'client:id,first_name,last_name,profile_picture',
            'bien:id,titre,type_bien',
        ])->findOrFail($id);

        $messages = $conversation->messages()
            ->with([
                'sender:id,first_name,last_name,profile_picture',
                'suppressions.user:id,first_name,last_name',
            ])
            ->orderBy('created_at', 'asc')
            ->paginate($request->integer('per_page', 100));

        return response()->json([
            'success'      => true,
            'conversation' => $this->formatConversationAdmin($conversation),
            'data'         => $messages->map(fn ($m) => $this->formatMessageAdmin($m)),
            'meta'         => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Notifie le destinataire du message :
     *  1. Broadcast Reverb (temps réel — badge + bulle) sur canal agent.{id} et user.{id}
     *  2. Notification in-app (base + push FCM) via NotificationService
     *  3. Email discret (sans contenu du message)
     */
    private function notifierDestinataire(
        Conversation $conversation,
        Message      $message,
        \App\Models\User $expediteur
    ): void {
        // Déterminer le destinataire : celui qui N'a PAS envoyé le message
        $destinataireId = $expediteur->id === $conversation->agent_id
            ? $conversation->client_id
            : $conversation->agent_id;

        $destinataire = \App\Models\User::find($destinataireId);
        if (! $destinataire) return;

        // ── 1. Broadcast Reverb temps réel ────────────────────────────────────
        try {
            broadcast(new NouveauMessageEvent(
                message:           $message,
                destinataireId:    $destinataireId,
                destinataireRole:  $destinataire->role,
            ));
        } catch (\Throwable $e) {
            Log::warning("[Messagerie] Broadcast NouveauMessageEvent échoué : {$e->getMessage()}");
        }

        // ── 2. Notification in-app + push FCM ─────────────────────────────────
        try {
            $expediteurNom = trim(
                ($expediteur->first_name ?? '') . ' ' . ($expediteur->last_name ?? '')
            ) ?: 'Quelqu\'un';

            $bienTitre = $conversation->bien?->titre;

            $titre   = "💬 Nouveau message de {$expediteurNom}";
            $notifMsg = $bienTitre
                ? "Concernant : {$bienTitre}"
                : 'Vous avez reçu un message sur ImmoPro.';

            app(NotificationService::class)->notify(
                user:          $destinataire,
                type:          'nouveau_message',
                titre:         $titre,
                message:       $notifMsg,
                data:          [
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $expediteur->id,
                    'sender_nom'      => $expediteurNom,
                    'bien_id'         => $conversation->bien_id ?? '',
                    'bien_titre'      => $bienTitre ?? '',
                ],
                // Pas d'email ici — géré séparément ci-dessous sans contenu
            );
        } catch (\Throwable $e) {
            Log::warning("[Messagerie] Notification in-app échouée : {$e->getMessage()}");
        }

        // ── 3. Email discret (sans contenu du message) ─────────────────────────
        // On envoie un email uniquement à l'agent (pas au client)
        // et uniquement si le message vient d'un client (pour ne pas spammer)
        if ($destinataire->role === 'agent' && $destinataire->email) {
            try {
                $conversation->load('bien:id,titre');
                Mail::to($destinataire->email)
                    ->queue(new NouveauMessageMail(
                        destinataire: $destinataire,
                        expediteur:   $expediteur,
                        conversation: $conversation,
                    ));
            } catch (\Throwable $e) {
                Log::warning("[Messagerie] Email NouveauMessage non envoyé : {$e->getMessage()}");
            }
        }
    }

    /**
     * Vérifie que l'utilisateur est bien un participant de la conversation.
     */
    private function autoriserParticipant(\App\Models\User $user, Conversation $conversation): void
    {
        if ($user->id !== $conversation->agent_id && $user->id !== $conversation->client_id) {
            abort(403, 'Accès non autorisé à cette conversation.');
        }
    }

    private function formatConversation(Conversation $conv, \App\Models\User $user): array
    {
        $autre = $conv->autreParticipant($user);

        return [
            'id'                  => $conv->id,
            'autre_participant'   => $autre ? [
                'id'              => $autre->id,
                'nom'             => trim($autre->first_name . ' ' . $autre->last_name),
                'photo'           => $autre->profile_picture,
            ] : null,
            'bien'                => $conv->bien ? [
                'id'    => $conv->bien->id,
                'titre' => $conv->bien->titre,
                'type'  => $conv->bien->type_bien,
            ] : null,
            'dernier_message'     => $conv->dernierMessage ? [
                'contenu'    => $conv->dernierMessage->contenu,
                'envoye_le'  => $conv->dernierMessage->created_at?->toIso8601String(),
                'par_moi'    => $conv->dernierMessage->sender_id === $user->id,
            ] : null,
            'messages_non_lus'    => $conv->messagesNonLus($user),
            'dernier_message_le'  => $conv->dernier_message_le?->toIso8601String(),
            'created_at'          => $conv->created_at?->toIso8601String(),
        ];
    }

    private function formatMessage(Message $message, \App\Models\User $user): array
    {
        return [
            'id'          => $message->id,
            'contenu'     => $message->contenu,
            'par_moi'     => $message->sender_id === $user->id,
            'sender'      => [
                'id'    => $message->sender->id,
                'nom'   => trim($message->sender->first_name . ' ' . $message->sender->last_name),
                'photo' => $message->sender->profile_picture,
            ],
            'delivre_le'  => $message->delivre_le?->toIso8601String(),
            'lu_le'       => $message->lu_le?->toIso8601String(),
            'envoye_le'   => $message->created_at?->toIso8601String(),
        ];
    }

    private function formatConversationAdmin(Conversation $conv): array
    {
        return [
            'id'                => $conv->id,
            'agent'             => $conv->agent ? [
                'id'    => $conv->agent->id,
                'nom'   => trim($conv->agent->first_name . ' ' . $conv->agent->last_name),
                'photo' => $conv->agent->profile_picture,
            ] : null,
            'client'            => $conv->client ? [
                'id'    => $conv->client->id,
                'nom'   => trim($conv->client->first_name . ' ' . $conv->client->last_name),
                'photo' => $conv->client->profile_picture,
            ] : null,
            'bien'              => $conv->bien ? [
                'id'    => $conv->bien->id,
                'titre' => $conv->bien->titre,
                'type'  => $conv->bien->type_bien,
            ] : null,
            'nb_messages'       => $conv->messages_count ?? null,
            'dernier_message'   => $conv->dernierMessage ? [
                'contenu'   => $conv->dernierMessage->contenu,
                'envoye_le' => $conv->dernierMessage->created_at?->toIso8601String(),
            ] : null,
            'dernier_message_le' => $conv->dernier_message_le?->toIso8601String(),
            'created_at'         => $conv->created_at?->toIso8601String(),
        ];
    }

    private function formatMessageAdmin(Message $message): array
    {
        $suppressions = $message->suppressions->map(fn ($s) => [
            'user'      => $s->user ? trim($s->user->first_name . ' ' . $s->user->last_name) : null,
            'pour_tous' => $s->pour_tous,
            'le'        => $s->supprime_le?->toIso8601String(),
        ]);

        return [
            'id'           => $message->id,
            'contenu'      => $message->contenu,
            'sender'       => $message->sender ? [
                'id'    => $message->sender->id,
                'nom'   => trim($message->sender->first_name . ' ' . $message->sender->last_name),
                'photo' => $message->sender->profile_picture,
                'role'  => $message->sender->role ?? null,
            ] : null,
            'lu_le'        => $message->lu_le?->toIso8601String(),
            'envoye_le'    => $message->created_at?->toIso8601String(),
            // Info sur les suppressions — visible uniquement par l'admin
            'suppressions' => $suppressions,
            'supprime_pour_tous' => $message->suppressions->where('pour_tous', true)->isNotEmpty(),
        ];
    }
}
