# Many Faced God (MFG)

**A dungeon mastering tool to help you take on the many faces of your world.**

Many Faced God is a web-based NPC (Non-Player Character) generator and manager for Dungeons & Dragons 5th Edition campaigns. Create, view, edit, delete, and organize NPCs with persistent storage.

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

5. Access the application at **http://localhost:8080**

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

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
