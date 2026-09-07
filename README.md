# Many Faced God (MFG)

**A dungeon mastering tool to help you take on the many faces of your world.**

Many Faced God is a web-based NPC (Non-Player Character) generator and manager for Dungeons & Dragons 5th Edition campaigns. Create, view, edit, delete, and organize NPCs with persistent storage — and serve their statblocks to other tools over a versioned read-only API.

> Part of a suite of self-hosted tabletop tools, each built in a different stack:
> **Many Faced God** (NPCs · Laravel/PHP) ·
> [Library of Netheril](https://github.com/Nayanaka-De-Silva/Library-Of-Netheril) (spells · TypeScript) ·
> [Bank of Vivaldi](https://github.com/Nayanaka-De-Silva/Bank-Of-Vivaldi) (inventory · Go) ·
> [Manticore Arena](https://github.com/Nayanaka-De-Silva/Manticore-Arena) (combat tracker · TypeScript).

## Features

- 🎭 **NPC Management** - Full CRUD operations for D&D 5e NPCs
- 📁 **Folder Organization** - Hierarchical folder structure to organize NPCs
- 🎲 **Random Generation** - Generate NPCs with random stats and abilities
- 📝 **Templates** - Save and reuse NPC templates
- 🔍 **Search & Filter** - Find NPCs by name, CR, alignment, or folder
- 📱 **Responsive Design** - Works on desktop, tablet, and mobile
- 🎨 **D&D-Style UI** - Cards styled like official D&D rulebooks

## Tech Stack

- **Framework:** Laravel 11
- **Database:** MySQL 8.0
- **Containerization:** Docker & Docker Compose
- **Frontend:** Bootstrap 5 + Blade Templates
- **Testing:** PHPUnit

## Quick Start

### Prerequisites

- Docker & Docker Compose installed

### Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd many-faced-god
   ```

2. Copy environment file:
   ```bash
   cp .env.example .env
   ```

3. Start the Docker containers:
   ```bash
   docker-compose up -d
   ```

4. Install dependencies and run migrations:
   ```bash
   docker-compose exec app composer install
   docker-compose exec app php artisan key:generate
   docker-compose exec app php artisan migrate
   docker-compose exec app php artisan db:seed  # Optional: load sample data
   ```

5. Access the application at **http://localhost:8765**

### Running Tests

```bash
docker-compose exec app php artisan test
```

## Database Schema

### NPCs Table
| Column | Type | Description |
|--------|------|-------------|
| name | string | NPC name |
| npc_type | string | e.g., "Medium Humanoid" |
| alignment | string | e.g., "Chaotic Good" |
| armor_class | integer | AC value |
| hit_points | integer | HP value |
| hit_dice | string | e.g., "4d8+8" |
| speed | string | e.g., "30 ft., fly 60 ft." |
| strength - charisma | integer | Ability scores (6 columns) |
| saving_throw_proficiencies | JSON | Array of proficient saves |
| skill_proficiencies | JSON | Array of proficient skills |
| damage_vulnerabilities | JSON | Array of damage vulnerabilities |
| damage_resistances | JSON | Array of damage resistances |
| damage_immunities | JSON | Array of damage immunities |
| condition_immunities | JSON | Array of condition immunities |
| senses | JSON | Array of senses with ranges |
| languages | JSON | Array of known languages |
| challenge_rating | string | CR value (e.g., "1/4", "5") |
| proficiency_bonus | integer | Proficiency bonus |
| folder_id | foreign key | Optional folder reference |
| is_template | boolean | Whether this is a template |

### Related Tables
- **folders** - Hierarchical NPC organization
- **npc_traits** - Passive traits/abilities
- **npc_actions** - Actions, bonus actions, reactions, legendary actions
- **npc_spellcasting** - Spellcasting abilities and spell lists

## API Routes

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/` | Dashboard |
| GET | `/npcs` | List NPCs |
| GET | `/npcs/create` | Create NPC form |
| POST | `/npcs` | Store new NPC |
| GET | `/npcs/{npc}` | View NPC |
| GET | `/npcs/{npc}/edit` | Edit NPC form |
| PUT | `/npcs/{npc}` | Update NPC |
| DELETE | `/npcs/{npc}` | Delete NPC |
| POST | `/npcs/{npc}/duplicate` | Duplicate NPC |
| POST | `/npcs/generate` | Generate random NPC |
| GET | `/folders` | List folders |
| GET | `/templates` | List templates |

### Public API (v1)

The routes above are the browser-facing Blade application. MFG also exposes a
separate, versioned, **read-only** JSON API under `/api/v1` for external
consumers — currently used by **Manticore Arena** to import NPC statblocks
into combat without the DM retyping them.

- **No authentication.** Docker/Tailscale network isolation is the trust
  boundary — do not expose `/api/v1` directly to the public internet.
- **Envelopes:** lists return `{"data":[...],"meta":{"page","pageSize","totalItems","totalPages"}}`;
  single resources return `{"data":{...}}`; errors return
  `{"error":{"code","message"}}`.
- **Caching:** `GET /api/v1/npcs/{id}` and `GET /api/v1/templates/{id}` serve
  an `ETag`, computed from the NPC and all of its child rows (traits,
  actions, spellcasting). Send it back as `If-None-Match` to get a `304`
  with no body. `ETag` is exposed cross-origin via
  `Access-Control-Expose-Headers` so browser clients can read it.
- **Challenge Rating:** always serialized as an object —
  `{"value","xp","xpIfDangerous"}` — never a bare number or a coerced `0`.
  A missing CR is honestly reported as `null`.
- **Note cards:** each NPC and template includes a `noteCards` array
  (`[{"id","title","description","sortOrder"}]`) in display order. A derived
  flat `notes` string (title + description blocks joined by `\n\n`) is also
  returned for backwards compatibility; new consumers should read `noteCards`
  directly.

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/v1/` | Discovery root — version, endpoint map, spec link |
| GET | `/api/v1/health` | Liveness probe |
| GET | `/api/v1/metadata` | Static vocabularies (alignments, skills, CR→XP, etc.), cached 24h |
| GET | `/api/v1/openapi.yaml` | The full contract, machine-readable |
| GET | `/api/v1/npcs` | Paginated NPC statblocks (excludes templates) |
| GET | `/api/v1/npcs/{id}` | One NPC statblock |
| GET | `/api/v1/templates` | Paginated templates |
| GET | `/api/v1/templates/{id}` | One template |
| GET | `/api/v1/folders` | Folder tree with counts |
| GET | `/api/v1/folders/{id}` | One folder, with breadcrumb and children |

The authoritative machine-readable contract is
[`openapi/v1.yaml`](openapi/v1.yaml), also served live at
`/api/v1/openapi.yaml`. An executable Postman collection covering the full
contract lives at
[`docs/api/postman/many-faced-god-api.postman_collection.json`](docs/api/postman/many-faced-god-api.postman_collection.json)
(paired environment: `docs/api/postman/many-faced-god.postman_environment.json`).

## NPC Creation Workflows

1. **From Scratch** - Complete manual form entry
2. **From Template** - Select a predefined template and customize
3. **From Existing NPC** - Clone an existing NPC and modify
4. **Random Generation** - Procedural generation based on criteria

## Development

### Project Structure

```
app/
├── Http/Controllers/      # Request handlers
├── Models/               # Eloquent models
└── Services/             # Business logic (NpcGenerator)

database/
├── factories/            # Model factories for testing
├── migrations/           # Database schema
└── seeders/             # Sample data

resources/views/
├── layouts/             # Main layout template
├── npcs/               # NPC views (index, show, create, edit)
├── folders/            # Folder views
└── templates/          # Template views

tests/
├── Feature/            # Integration tests
└── Unit/              # Unit tests
```

### Running Tests Locally

```bash
# Run all tests
docker-compose exec app php artisan test

# Run with coverage
docker-compose exec app php artisan test --coverage

# Run specific test file
docker-compose exec app php artisan test tests/Feature/NpcControllerTest.php
```

## Deployment

For deployment on external servers using Docker, see [DEPLOYMENT.md](./DEPLOYMENT.md) for comprehensive instructions including:

- Configuration for production environments
- SSL/TLS setup with reverse proxy
- Database backup strategies
- Scaling with Redis/Memcached
- Troubleshooting guide
- Maintenance procedures

Quick summary:
1. Update `.env` with your domain and credentials
2. Set `APP_ENV=production` and `APP_DEBUG=false`
3. Generate a new `APP_KEY`
4. Run `docker-compose up -d` to deploy
5. Configure your reverse proxy for SSL (optional but recommended)

## License

This project's source is original and licensed under the [MIT License](LICENSE).

Dungeons & Dragons is a trademark of Wizards of the Coast. This is a personal,
non-commercial tool and is not affiliated with or endorsed by Wizards of the Coast.
No rules text or stat blocks from Wizards of the Coast products are committed to
this repository — NPC content is generated procedurally or entered by the operator.
