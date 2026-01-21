# Many Faced God (MFG)

**Tagline:** A dungeon mastering tool to help you take on the many faces of your world.

## Project Summary

Many Faced God (MFG) is a web-based NPC (Non-Player Character) generator and manager for Dungeons & Dragons 5th Edition campaigns. The application enables users to create, view, edit, delete, and organize NPCs with persistent storage. Currently supports D&D 5e (2014); future system support is planned.

## Core Objectives

- Generate and manage NPCs for D&D 5e campaigns
- Provide intuitive CRUD (Create, Read, Update, Delete) operations for NPC data
- Enable organization of NPCs into hierarchical folder structures
- Support multiple creation workflows (from scratch, templates, existing NPCs, generators)
- Ensure responsive, professional user experience across desktop and mobile devices

## Technical Requirements

### Technology Stack
- **Framework:** Laravel PHP Framework
- **Database:** MySQL or PostgreSQL
- **Containerization:** Docker with provided Dockerfile
- **Testing:** PHPUnit for unit and integration tests

### Code Quality Standards
- Follow Object-Oriented Programming (OOP) principles
- Organize code into modular, reusable classes and libraries
- Maintain comprehensive unit test coverage for all components
- Implement integration tests for cross-component functionality
- Adhere to PSR-12 PHP coding standards

### Documentation
- Create and maintain comprehensive README.md with setup and usage instructions
- Document all API endpoints and database schemas
- Provide deployment and troubleshooting guides

## Functional Requirements

### Core Features
- **NPC Management:** Create, read, update, delete NPCs with persistent storage
- **User Input Validation:** Validate all required fields and enforce data integrity
- **Search & Filter:** Enable filtering NPCs by multiple criteria
- **Responsive Design:** Support desktop, tablet, and mobile devices
- **Folder Organization:** Support hierarchical organization of NPCs with nested folder support

### User Interface
- **NPC Cards:** Display NPCs as cards with basic information; expand on click for full details
- **Card Design:** Match D&D Core rulebook styling conventions
- **Navigation Menu:** Persistent sidebar menu providing access to:
  - Generated NPCs
  - Templates
  - Generators
  - Settings
- **NPC Creation Button:** Bottom-screen action button to initiate NPC creation workflow

### NPC Creation Workflows
Users can create NPCs using one of four methods:
1. **From Scratch:** Complete manual form entry with all NPC details
2. **From Template:** Select predefined template and customize fields
3. **From Existing NPC:** Clone existing NPC and modify as needed
4. **From Generator:** Procedural generation based on selected criteria

Post-creation NPCs appear in the main view alongside existing NPCs.

### NPC Information
The following information can be stored for a D&D 5e (2014) NPC:
- Name
- NPC Type (e.g. Medium Humanoid)
- Alignment (e.g. Chaotic Good)
- Armor Class (with Armor Type)
- Hit Points (Can be generated using dice notation)
- Speed (in ft)
- Attributes (STR, DEX, CON, INT, WIS, CHA)
- Saving Throw Proficiencies
- Skill Proficiencies
  - Acrobatics
  - Animal Handling
  - Arcana
  - Atheltics
  - Deception
  - History
  - Insight
  - Intimidation
  - Investigation
  - Medicine
  - Nature
  - Perception
  - Performance
  - Persuasion
  - Religion
  - Sleight of Hand
  - Stealth
  - Survival
- Damage Vulnerabilities, Resistances & Immunities
  - Bludgeoning
  - Piercing
  - Slashing
  - Magical Bludgeoning
  - Magical Piercing 
  - Magical Slashing
  - Fire
  - Cold
  - Acid
  - Force
  - Lightning
  - Necrotic
  - Poison
  - Psychic
  - Radiant
  - Thunder
- Condition Immunities
  - Blinded
  - Charmed
  - Deafened
  - Frightened
  - Grappled
  - Incapacitated
  - Invisible
  - Paralyzed
  - Petrified
  - Poisoned
  - Prone
  - Restrained
  - Stunned
  - Unconscious
  - Exhaustion
- Senses
  - Types (e.g., Darkvision, Tremorsense)
  - Range (in ft)
- Languages
- Challenge Rating
- Proficiency Bonus
- Traits (e.g. Darkvision, Keen Sense, Shapechanger, etc)
- Actions (e.g. Melee Weapon Attack, Ranged Weapon Attack, Spellcasting, etc)

The system must also allow for spellcasting NPCs, reactions, bonus actions and Legendary Actions.

## Standard Operating Procedures

### Development Workflow
1. **Branch Strategy:** Use Git Flow (main, develop, feature/*, bugfix/*, release/*)
2. **Commit Messages:** Follow conventional commits format (feat:, fix:, docs:, test:, refactor:)
3. **Code Review:** All pull requests require minimum one approval before merge
4. **Testing Requirement:** New features must include unit tests (minimum 80% coverage)
5. **Documentation:** Update README and inline documentation with feature additions

### Environment Setup
- **Local Development:** Use provided Docker Compose configuration (`docker-compose.yml`)
- **Database Migrations:** Run migrations automatically on container startup
- **Seed Data:** Load test fixtures for development and staging environments
- **Environment Variables:** Use `.env` files (never commit `.env.local`); document required variables in `.env.example`

### Deployment Process
1. Deploy to staging environment; verify functionality
2. Run full test suite and code quality checks
3. Obtain approval from project maintainer
4. Tag release with semantic versioning (v1.0.0)
5. Deploy Docker image to production
6. Verify deployment health; monitor error logs

### Database Management
- All database changes must be version-controlled via migrations
- Migrations must be idempotent and reversible
- Document schema changes in CHANGELOG.md
- Test rollback procedures before production deployment

## Best Practices

### Code Quality
- **Linting:** Run PHP CodeSniffer (phpcs) before commits
- **Static Analysis:** Use PHPStan for type checking
- **Code Style:** Follow PSR-12 standard; use automated formatters (PHP-CS-Fixer)
- **Naming Conventions:** Use descriptive, intention-revealing names
  - Classes: PascalCase (e.g., `NPCGenerator`)
  - Methods/functions: camelCase (e.g., `generateNPC()`)
  - Constants: UPPER_SNAKE_CASE (e.g., `MAX_NPC_NAME_LENGTH`)

### Testing
- **Unit Tests:** Test individual methods and edge cases
- **Integration Tests:** Verify NPC creation workflows end-to-end
- **Feature Tests:** Test user workflows through HTTP requests
- **Test Data:** Use factories and seeders for consistent test fixtures
- **Target Coverage:** Maintain ≥80% code coverage for core features

### Security
- **Input Validation:** Sanitize and validate all user input server-side
- **SQL Injection Prevention:** Use prepared statements and Laravel query builder
- **CSRF Protection:** Implement CSRF tokens in all forms
- **Authentication:** Implement role-based access control (RBAC) for future multi-user support
- **Dependency Scanning:** Regularly run `composer audit` to check for vulnerabilities

### Performance
- **Database Indexing:** Index frequently queried columns (NPC name, campaign ID)
- **Eager Loading:** Use Laravel relationships with eager loading to minimize queries
- **Caching:** Implement caching for static templates and generators
- **Pagination:** Paginate large NPC lists (default 25 per page)
- **Query Optimization:** Use `explain` to analyze slow queries

### Documentation
- **Code Comments:** Document complex logic and business rules; avoid obvious comments
- **API Documentation:** Maintain OpenAPI/Swagger specification
- **Architecture Decisions:** Document ADRs (Architecture Decision Records) for major choices
- **Changelog:** Maintain CHANGELOG.md with user-facing changes per version
- **Runbooks:** Document common maintenance tasks and troubleshooting procedures

### Version Control
- **Commit Frequency:** Make small, atomic commits with clear intent
- **Branch Naming:** Use descriptive names (`feature/npc-search`, `bugfix/template-validation`)
- **Pull Request Descriptions:** Include what changed, why it changed, and how to test
- **Rebase Strategy:** Rebase feature branches before merging to maintain linear history
