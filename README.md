# Médiathèque de Parakou : API de prêts

API REST (Laravel + SQLite) qui permet à un bibliothécaire d'enregistrer des adhérents, des livres, des emprunts et des retours, et à un adhérent de consulter ses propres emprunts.

## Prérequis

- PHP 8.3 ou plus (version testée : 8.5.11) avec les extensions `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`
- Composer 2
- Aucune base de données à installer : SQLite est un simple fichier

## Installation

Commandes à lancer à la racine du dépôt (invite de commandes `cmd.exe` sous Windows) :

```
git clone https://github.com/jeromegsa/biblioApp.git
cd biblioApp
composer install
copy .env.example .env
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate --force
```

Sous Linux ou macOS : `cp .env.example .env` et `touch database/database.sqlite` à la place des deux commandes Windows.

## Démarrage

```
php artisan serve
```

L'API répond sur `http://127.0.0.1:8000/api`.

## Accès par profil

L'authentification est un jeton transmis dans l'en-tête `Authorization: Bearer <jeton>`.

| Profil | Jeton | Comment l'obtenir |
|---|---|---|
| Bibliothécaire | `ABCDEFs` | valeur de `BIBLIOTHECAIRE_TOKEN` dans `.env` (copiée de `.env.example`) |
| Adhérent | généré à la création | renvoyé dans le champ `api_token` de la réponse de `POST /api/adherents` |

Pour tester le profil adhérent : créer un adhérent avec le jeton du bibliothécaire, puis utiliser son `api_token`.

## Description de l'API

Toutes les routes sont préfixées par `/api`. Les erreurs sont toujours en JSON, même sans en-tête `Accept`, au format `{ "message": "..." }`. Pour une saisie invalide : `{ "message": "...", "errors": { "champ": ["..."] } }`.

### Codes HTTP

| Code | Situation |
|---|---|
| 401 | jeton absent ou invalide |
| 403 | action réservée au bibliothécaire demandée par un adhérent ; un adhérent qui consulte les emprunts d'un autre |
| 404 | adhérent, livre ou emprunt introuvable |
| 409 | règle de gestion violée (R4, R5, R6, R7) |
| 422 | saisie invalide (format, borne, doublon de carte ou d'ISBN, statut de filtre inconnu) |

### Routes

| Verbe | Chemin | Accès | Rôle |
|---|---|---|---|
| POST | `/api/adherents` | bibliothécaire | enregistrer un adhérent |
| POST | `/api/livres` | bibliothécaire | enregistrer un livre |
| POST | `/api/emprunts` | bibliothécaire | enregistrer un emprunt |
| POST | `/api/emprunts/{id}/retour` | bibliothécaire | enregistrer un retour (date du jour) |
| GET | `/api/livres/{isbn}` | tout utilisateur authentifié | consulter un livre et ses exemplaires disponibles |
| GET | `/api/adherents/{carte}/emprunts` | bibliothécaire ou adhérent concerné | lister les emprunts, filtre `?statut=`, 20 par page (`?page=2`) |

### Exemples (`curl.exe`)

Ces commandes sont écrites pour l'invite de commandes `cmd.exe`, avec le serveur lancé. Dans PowerShell, l'échappement des guillemets du corps JSON diffère : utiliser `cmd.exe`.

Enregistrer un adhérent (201, la réponse contient `api_token`) :

```
curl.exe -X POST http://127.0.0.1:8000/api/adherents -H "Authorization: Bearer ABCDEFs" -H "Content-Type: application/json" -d "{\"carte\":\"00000001\",\"nom\":\"Awa Dossou\",\"email\":\"awa@exemple.bj\"}"
```

Enregistrer un livre (201) :

```
curl.exe -X POST http://127.0.0.1:8000/api/livres -H "Authorization: Bearer ABCDEFs" -H "Content-Type: application/json" -d "{\"isbn\":\"9780000000001\",\"titre\":\"Les Soleils des independances\",\"exemplaires\":3}"
```

Enregistrer un emprunt (201) :

```
curl.exe -X POST http://127.0.0.1:8000/api/emprunts -H "Authorization: Bearer ABCDEFs" -H "Content-Type: application/json" -d "{\"carte\":\"00000001\",\"isbn\":\"9780000000001\"}"
```

Consulter un livre (200, avec `exemplaires_disponibles`) :

```
curl.exe http://127.0.0.1:8000/api/livres/9780000000001 -H "Authorization: Bearer ABCDEFs"
```

Enregistrer le retour d'un emprunt (200 ; remplacer `1` par l'`id` renvoyé à la création de l'emprunt) :

```
curl.exe -X POST http://127.0.0.1:8000/api/emprunts/1/retour -H "Authorization: Bearer ABCDEFs"
```

Lister les emprunts d'un adhérent, du plus récent au plus ancien (200, 20 par page) :

```
curl.exe http://127.0.0.1:8000/api/adherents/00000001/emprunts -H "Authorization: Bearer ABCDEFs"
```

Même liste filtrée par statut (une valeur inconnue donne 422) :

```
curl.exe "http://127.0.0.1:8000/api/adherents/00000001/emprunts?statut=en_cours" -H "Authorization: Bearer ABCDEFs"
```

Un adhérent utilise son propre `api_token` à la place de `ABCDEFs` et ne peut consulter que sa carte.

Valeurs de `statut` d'un emprunt : `en_cours`, `rendu`, `en_retard`.

## Modèle de données

| Table | Colonnes |
|---|---|
| `adherents` | `id`, `carte` (chaîne de 8, unique), `nom` (100), `email` (facultatif), `api_token` (unique) |
| `livres` | `id`, `isbn` (chaîne de 13, unique), `titre` (150), `exemplaires` (entier de 1 à 20) |
| `emprunts` | `id`, `adherent_id`, `livre_id`, `date_emprunt`, `date_echeance`, `date_retour` (nul tant que non rendu) |

Le statut d'un emprunt n'est pas stocké : il se calcule à partir des dates.

## Règles de gestion

| Règle | Contenu | Code si violée | Couverture |
|---|---|---|---|
| R1 | carte exactement 8 chiffres et unique ; nom de 2 à 100 caractères ; email facultatif valide ; isbn exactement 13 chiffres et unique ; titre de 150 caractères au plus ; exemplaires entier de 1 à 20 | 422 | `test_r1_*` |
| R2 | authentification obligatoire ; actions d'enregistrement réservées au bibliothécaire ; un adhérent ne voit que ses emprunts | 401 ou 403 | `test_r2_*` |
| R3 | échéance = date du jour d'emprunt + 14 jours, date du Bénin (fuseau `Africa/Porto-Novo`) | sans objet | `test_r3_*` |
| R4 | au plus 3 emprunts non rendus à la fois | 409 | `test_r4_*` |
| R5 | un emprunt non rendu est en retard quand la date du jour est strictement postérieure à l'échéance ; un adhérent en retard ne peut plus emprunter | 409 | `test_r5_*` (date simulée) |
| R6 | un livre dont tous les exemplaires sont prêtés ne peut pas être emprunté | 409 | `test_r6_*` |
| R7 | un emprunt rendu ne peut plus être rendu ni modifié (aucune route de modification n'existe) ; le retour enregistre la date du jour | 409 | `test_r7_*` |
| R8 | liste du plus récent au plus ancien ; statut de filtre inconnu refusé avec les valeurs acceptées | 422 | `test_r8_*` |
| R9 | erreur de saisie ou action interdite : message clair et code adapté | voir tableau des codes | vérifié dans chaque test |

## Hypothèses et choix de conception

- **Authentification** : jeton Bearer. Le jeton du bibliothécaire est fixé dans `.env`. Celui d'un adhérent est généré à sa création et renvoyé dans la réponse de création.
- **Identifiants** : un adhérent est identifié par sa `carte`, un livre par son `isbn`, un emprunt par son `id`.
- **Types** : `carte` et `isbn` sont des chaînes JSON (avec guillemets) pour conserver les zéros initiaux. `exemplaires` doit être un nombre JSON : `"5"` en texte est refusé.
- **Doublons** : une carte ou un ISBN déjà enregistré donne 422 (règle de format R1).
- **Emprunt** : le corps contient la `carte` et l'`isbn`. Carte ou ISBN inconnu : 404. Ordre des contrôles : 404, puis R5, puis R4, puis R6.
- **Retard** : un emprunt dont l'échéance est le jour même n'est pas en retard (comparaison stricte). Un emprunt en retard compte dans la limite de 3 de R4.
- **Même livre** : un adhérent peut emprunter plusieurs fois le même livre, l'énoncé ne l'interdit pas.
- **Statuts** : valeurs sans accents `en_cours`, `rendu`, `en_retard` (correspondance avec l'énoncé : « en cours », « rendu », « en retard »).

- **Accès aux emprunts** : un adhérent qui demande les emprunts d'une autre carte reçoit 403, même si cette carte n'existe pas (pour ne pas révéler quelles cartes existent). Le bibliothécaire reçoit 404 pour une carte inconnue.
- **Pagination** : la liste des emprunts renvoie 20 éléments par page, avec les champs `data`, `links` et `meta` de Laravel.

## Tests

```
php artisan test
```

Les tests (`tests/Feature/PretsTest.php`) utilisent une base SQLite en mémoire : ils ne touchent pas la base de travail. Ils couvrent les règles R1 à R8, dont le retard (R5), testé avec une date simulée (`travelTo`) : le jour de l'échéance l'emprunt n'est pas en retard, le lendemain il l'est et l'emprunt est refusé.

## État d'avancement

**Fonctionne** : toutes les fonctions attendues et les règles R1 à R9 : enregistrement d'un adhérent, d'un livre, d'un emprunt et d'un retour, consultation d'un livre avec ses exemplaires disponibles, liste des emprunts d'un adhérent avec filtre par statut, authentification des deux profils.

**Manque, et pourquoi** : comptage par statut, prolongation unique de 7 jours et écran web simple. Ces bonus ont été écartés volontairement pour garder le temps de l'épreuve au socle, aux tests et au README. Pas de bogue connu non corrigé.

**Bonus réalisés** : tests automatisés des règles (avec simulation de date) et pagination de 20 éléments au plus.
