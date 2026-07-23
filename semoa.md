CashPay_Api V2.0
General presentation
This documentation presents the procedure for integrating digital payment solutions into your e-commerce platform. The different resources available:

Test authentication

Create a bill

Get bill's details

Get bill's list

Get available payments gateways

Callback Url

Access to API:
Environement: Sandbox
Endpoint: https://sandbox.semoa-payments.com/api

Errors
CashPay uses HTTP status codes to indicate success or failure of API calls. Status codes in the 2xx range indicate success, 4xx range indicate error in the information provided, and 5xx range indicate server side errors. The following table lists some commonly used HTTP status codes.

HTTP STATUS CODES
View More
Status Code	Description
200	OK
201	Created
204	No content
400	Bad request
401	Unauthorized
403	Forbidden (Unauthorised access)
404	URL not found
405	Method not allowed (Method called is not supported for the API invoked)
413	Payload Too Large
415	Unsupported Media Type
422	Unprocessable Entity
429	Too Many Requests
500	Internal error
Abbreviations
In the rest of this document, the following abbreviations will be used.

Abbreviation	Meaning
M	Mandatory
M*	Mandatory except if the complementary parameter is defined
O	Optional
O*	Optional if XOR parameter is not present
Run queries to CashPay
Authentication
In version 2.0 we have impement a new type of authentication with Oauth2, but you can also use Cashpay Authentication.

Oauth 2
Our APIs uses the OAuth 2.0 protocol for authentication/authorization. All access to our APIs requires authentication. Authenticated requests require an access_token.

Cashpay Auth
The security of Cashpay Api with this type of authentication is carried by 4 parameters in the headers of the request: login, apireference, salt and apisecure.

Below the description :

Parameter	Type	Description
login	String	Your login on the platform
apireference	Integer	The identifier of the key API generated on your interface
salt	Integer	Any unique and random numerical value
apisecure	String	Token to strongly identify a specific transaction. This is the concatenation of login, apikey and salt hashing in sha-256: sha256(login+apiKey+ salt)
NB: For each request you must generate an identifier (salt) which will be an unique integer. This will make your request unique to the API.

POST
add contacts
{{endpoint}}/contacts
This resource is used to add contacts to a client.

Parameters:

Name	Type	M/O	Description
contacts	Array of objects	M	Array of contacts
- phone	String	M	Contact's phone number
- lastname	String	O	Contact's name
- firstname	String	O	Contact's surname
- email	String	O	Contact's email
client_phone	String	M	Client's Phone number ​
Response :

Name	Type	Description
status	String	Status of the request : "success" or "error"
message	String	Description of the status
HEADERS
login
{{login}}

apisecure
{{api_secure}}

apireference
{{api_reference}}

salt
{{salt}}

Content-Type
application/json

Body
raw
View More
{	
	"contacts":[
        {"phone": "+22898486419",
        "firstname": "UNKNOWN",
        "lastname": "UNKNOWN",
        "email": "unknown@gmail.com"
        },
        {
        "phone": "+22890833218",
        "firstname": "UNKNOWN",
        "lastname": "UNKNOWN"
        }
    ],
    "client_phone": "+22890112783"
}
Example Request
add contacts
View More
php
<?php
$client = new Client();
$headers = [
  'login' => '{{login}}',
  'apisecure' => '{{api_secure}}',
  'apireference' => '{{api_reference}}',
  'salt' => '{{salt}}',
  'Content-Type' => 'application/json'
];
$body = '{
  "contacts": [
    {
      "phone": "+22898486419",
      "firstname": "UNKNOWN",
      "lastname": "UNKNOWN",
      "email": "unknown@gmail.com"
    },
    {
      "phone": "+22890833218",
      "firstname": "UNKNOWN",
      "lastname": "UNKNOWN"
    }
  ],
  "client_phone": "+22890112783"
}';
$request = new Request('POST', '{{endpoint}}/contacts', $headers, $body);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
201 Created
Example Response
Body
Headers (8)
json
{
  "status": "success",
  "message": "Contact registered"
}
TPOS
POST
Create an order
{{endpoint}}/tpos/orders
Input Parameters
View More
Name	Type	M/O	Description
amount	Integer	M	Amount to be paid
merchant_reference	String (255 char)	M	Merchant reference
description	String (Text)	O	Description to be show on bill generated
currency	String	M	ISO 4217 (Example: XOF)
callback_url	String (255 char)	O	Cashpay will make a post on your callback url , when an update will be performed on you order ​
client {}	Object	M	Represents the customer’s information.
client.phone	String	M	Client's phone number
client.lastname	String (255 char)	O	Client's lastname
client.firstname	String(255 char)	O	Client's firstname
client.email	String (255 char)	O	Client's email
client.city	String	O	Client's city
client.country	String (255 char)	O	Client's country
client.address1	String (255 char)	O	Client's fist address
client.address2	String (Text)	O	Client's second address
gateway {}	Object	M	
gateway.reference	String	M	Gateway reference you want to use to pay the bill
ledger {}	Object	M	
ledger.reference	String	M	Ledger reference
Response :
View More
Name	Type	Description
state	String	Bill States Possible Values:
Pending, Paid, Error, Canceled, Partial, Excess
date_create	DateTime	Bill creation date
message	String	Description
code	String	Short code generated and associated to the bill. Might be already used on a previous bill but will be set to another status than “Pending” or “Partial”.
order_reference	String	CashPay’s internal unique reference
merchant_reference	String	Merchant Reference
amount	String	Bill amount
bill_url	String	URL of the bill generated on CashPay’s front Office.
qrcode_bill_url	String	Qrocde Url of the bill created.
currency	String	Bill currency
client {}	Object	Customer infos
client.phone	String	Customer's phone number
client.lastname	String	
client.firstname	String	
ledger {}	Object	
ledger.reference	String	Ledger reference
payments_method []	Array of Object	Gateway’s list
action	String	the action to follow to proceed with the payment (Ussd code or a link)
method	String	Payment method: USSD, PUSH_USSD, MOBBILE_APP, or REDIRECT_URL
gateway	String	Gateway name
description	String	Description
reference	String	Gateway Unique ID
AUTHORIZATION
Bearer Token
Token
<token>

HEADERS
Content-Type
application/json

Authorization
Bearer Token eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJPV2pmTktHcVl5b2VFd2FNV0d1UFNTdkZaVW5jVjc1aUtDLXMxMjRQZThJIn0.eyJleHAiOjE3MTkzMTYyNzAsImlhdCI6MTcxOTMxMjY3MCwianRpIjoiMjNiMzQ1OGEtNDI4NS00YmQ0LTk3OGQtZWUwNzMzMGQ0YjA5IiwiaXNzIjoiaHR0cHM6Ly9sb2NrLnNlbW9hLWhvc3RpbmcuY29tL3JlYWxtcy9EZXYuZW52IiwiYXVkIjoiYWNjb3VudCIsInN1YiI6IjU2ZTFmYWIyLTRiNjUtNDY1MC05YTdjLTNmZTI0MWQyODU3MiIsInR5cCI6IkJlYXJlciIsImF6cCI6ImNhc2hwYXkiLCJzZXNzaW9uX3N0YXRlIjoiY2Y4Y2FmM2EtMDg4My00Y2NkLWE2ZTktNDY5Y2EwNjE2ZTY5IiwiYWNyIjoiMSIsImFsbG93ZWQtb3JpZ2lucyI6WyIqIl0sInJlYWxtX2FjY2VzcyI6eyJyb2xlcyI6WyJkZWZhdWx0LXJvbGVzLWRldi5lbnYiLCJST0xFX1VTRVIiLCJvZmZsaW5lX2FjY2VzcyJdfSwicmVzb3VyY2VfYWNjZXNzIjp7ImFjY291bnQiOnsicm9sZXMiOlsibWFuYWdlLWFjY291bnQiLCJtYW5hZ2UtYWNjb3VudC1saW5rcyIsInZpZXctcHJvZmlsZSJdfX0sInNjb3BlIjoicm9sZXMgb2ZmbGluZV9hY2Nlc3MgcHJvZmlsZSBpbnRlcm5hbF9hY2Nlc3MgZW1haWwgY2xpZW50X2FjY2VzcyIsInNpZCI6ImNmOGNhZjNhLTA4ODMtNGNjZC1hNmU5LTQ2OWNhMDYxNmU2OSIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwiYWRkcmVzcyI6e30sIm5hbWUiOiJkZW1vMSBhcGkiLCJwcmVmZXJyZWRfdXNlcm5hbWUiOiJhcGlfY2FzaHBheS5kZW1vIiwiZ2l2ZW5fbmFtZSI6ImRlbW8xIiwiZmFtaWx5X25hbWUiOiJhcGkiLCJlbWFpbCI6ImRlbW8xQGdtYWlsLmNvbSJ9.lMwCOrs5Pry8Flb3yl81CnO8Ome22Y2ThHHrTTnvx5UUA3K3yj69i3xrlx8VcxHy3XMIVC8yYZfaFEPSh8AlmwTQ_V50xNyKD0LHfYT9ePRRSZo_mEyKnYp9_qzlbVISzgBzcBabn0KHgu_CDcDcwz8yzjhi0cJRjMAc-vx_kXrORenpIOaREQks8pEh3nXcPkBODcmfT0jsd4gNIC1ei7jgfZdD7BraEFolztRiNEUWO7G3eLO2O7bh0zNM8Bxrj-l_u4wsdNZFiuvUWqB7Y87a9L7t2AmvDl1iBQ9ynhbRcS3gcKgkTPKTvzccSLiQG9F0IsBMYU5qpAYrDe15-g

Body
raw
View More
{
    "amount": 200,
	"client": {
		"phone": "+22890112783"
	},
    "gateway": {
        "reference": "14f4597d-ef96-4263-8107-1e1970959133"
    },
    "ledger": {
        "reference":"de7a9b8e-74be-4ced-a263-7323e242cf19"
    },
    "currency": "XOF",