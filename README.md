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
| POST | `/api/emprunts/{id}/retour` | bibliothécaire | enregistrer un retour (à venir) |
| GET | `/api/livres/{isbn}` | tout utilisateur authentifié | consulter un livre et ses exemplaires disponibles |
| GET | `/api/adherents/{carte}/emprunts` | bibliothécaire ou adhérent concerné | lister les emprunts (à venir) |

### Exemples (`curl.exe`)

Enregistrer un adhérent (201, la réponse contient `api_token`) :

```
curl.exe -X POST http://127.0.0.1:8000/api/adherents -H "Authorization: Bearer ABCDEFs" -H "Content-Type: application/json" -d "{\"carte\":\"00000001\",\"nom\":\"Awa Dossou\",\"email\":\"awa@exemple.bj\"}"
```

Enregistrer un livre (201) :

```
curl.exe -X POST http://127.0.0.1:8000/api/livres -H "Authorization: Bearer ABCDEFs" -H "Content-Type: application/json" -d "{\"isbn\":\"9780000000001\",\"titre\":\"Les Soleils des indépendances\",\"exemplaires\":3}"
```

Enregistrer un emprunt (201) :

```
curl.exe -X POST http://127.0.0.1:8000/api/emprunts -H "Authorization: Bearer ABCDEFs" -H "Content-Type: application/json" -d "{\"carte\":\"00000001\",\"isbn\":\"9780000000001\"}"
```

Consulter un livre (200, avec `exemplaires_disponibles`) :

```
curl.exe http://127.0.0.1:8000/api/livres/9780000000001 -H "Authorization: Bearer ABCDEFs"
```

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
| R1 | carte exactement 8 chiffres et unique ; nom de 2 à 100 caractères ; email facultatif valide ; isbn exactement 13 chiffres et unique ; titre de 150 caractères au plus ; exemplaires entier de 1 à 20 | 422 | vérifié par `curl.exe` |
| R2 | authentification obligatoire ; actions d'enregistrement réservées au bibliothécaire ; un adhérent ne voit que ses emprunts | 401 ou 403 | vérifié par `curl.exe` |
| R3 | échéance = date du jour d'emprunt + 14 jours, date du Bénin (fuseau `Africa/Porto-Novo`) | sans objet | vérifié par `curl.exe` |
| R4 | au plus 3 emprunts non rendus à la fois | 409 | vérifié par `curl.exe` |
| R5 | un emprunt non rendu est en retard quand la date du jour est strictement postérieure à l'échéance ; un adhérent en retard ne peut plus emprunter | 409 | vérifié par `curl.exe` |
| R6 | un livre dont tous les exemplaires sont prêtés ne peut pas être emprunté (à venir) | 409 | à venir |
| R7 | un emprunt rendu ne peut plus être rendu ni modifié (à venir) | 409 | à venir |
| R8 | liste du plus récent au plus ancien ; statut de filtre inconnu refusé (à venir) | 422 | à venir |
| R9 | erreur de saisie ou action interdite : message clair et code adapté | voir tableau des codes | vérifié par `curl.exe` |

## Hypothèses et choix de conception

- **Authentification** : jeton Bearer. Le jeton du bibliothécaire est fixé dans `.env`. Celui d'un adhérent est généré à sa création et renvoyé dans la réponse de création.
- **Identifiants** : un adhérent est identifié par sa `carte`, un livre par son `isbn`, un emprunt par son `id`.
- **Types** : `carte` et `isbn` sont des chaînes JSON (avec guillemets) pour conserver les zéros initiaux. `exemplaires` doit être un nombre JSON : `"5"` en texte est refusé.
- **Doublons** : une carte ou un ISBN déjà enregistré donne 422 (règle de format R1).
- **Emprunt** : le corps contient la `carte` et l'`isbn`. Carte ou ISBN inconnu : 404. Ordre des contrôles : 404, puis R5, puis R4, puis R6.
- **Retard** : un emprunt dont l'échéance est le jour même n'est pas en retard (comparaison stricte). Un emprunt en retard compte dans la limite de 3 de R4.
- **Même livre** : un adhérent peut emprunter plusieurs fois le même livre, l'énoncé ne l'interdit pas.
- **Statuts** : valeurs sans accents `en_cours`, `rendu`, `en_retard` (correspondance avec l'énoncé : « en cours », « rendu », « en retard »).

## Tests

Les tests automatisés ne sont pas encore écrits (voir l'état d'avancement). Les règles R1 à R5 ont été vérifiées à la main avec `curl.exe`.

## État d'avancement

**Fonctionne** : authentification et rôles (R2), enregistrement d'un adhérent, d'un livre et d'un emprunt (R1, R3, R4, R5), consultation d'un livre avec ses exemplaires disponibles.

**Manque (état de ce premier jet), et pourquoi** : blocage d'un livre sans exemplaire disponible (R6), retour d'un emprunt (R7), liste des emprunts d'un adhérent avec filtre et tri (R8), tests automatisés. Ces éléments sont planifiés dans l'ordre des règles et seront mis à jour ici. Raison : temps limité de l'épreuve.

**Bonus réalisés** : aucun pour l'instant.
