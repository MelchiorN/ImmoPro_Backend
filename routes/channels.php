<?php

use Illuminate\Support\Facades\Broadcast;

// ── Channel privé utilisateur générique (notifications, mises à jour propres) ──
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── Channel privé admin — accessible uniquement aux admins ──────────────────
Broadcast::channel('admin', function ($user) {
    return $user->role === 'admin';
});

// ── Channel privé agent — accessible uniquement à l'agent concerné ──────────
Broadcast::channel('agent.{agentId}', function ($user, $agentId) {
    return (string) $user->id === (string) $agentId && $user->role === 'agent';
});

// ── Channel privé utilisateur — accessible uniquement à l'utilisateur lui-même
// Utilisé pour : notifications in-app, statut de bien, visites client/proprio
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
