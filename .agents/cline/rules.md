# Cline Rules for RIS Project

## CRITICAL: Rules Hierarchy

**Copilot Instructions are the BASE - Cline Rules are ADDITIONAL**

1. **PRIMARY RULES:** All coding standards in `.github/copilot/instructions/` MUST be followed at all times
2. **SECONDARY RULES:** Cline rules below provide additional project-specific guidance, tools, and AI agent workflows
3. **PRECEDENCE:** In case of any perceived conflict, Copilot instructions take precedence
4. **RULE MODIFICATIONS:** 
   - **MANDATORY PRE-CHECK:** Before adding/modifying ANY rule in this file, you MUST:
     1. Read ALL files in `.github/copilot/instructions/` directory
     2. Verify the proposed rule does NOT contradict any Copilot instruction
     3. Document which Copilot instruction files you checked
   - When asked to add/modify rules in Cline rules that would contradict Copilot instructions, you MUST:
     - **STOP immediately** - do NOT make the change
     - Clearly flag the contradiction to the user
     - Reject the request
     - Explain which specific Copilot rule would be violated
     - Quote the exact conflicting rule from the Copilot instructions
     - Suggest modifying Copilot instructions instead if that's the user's intent
   - **IMPORTANT DISTINCTION:** You MAY be asked to write code that doesn't comply with Copilot rules for specific one-time tasks (e.g., debugging, testing, legacy code). This is acceptable for code execution, but such exceptions CANNOT become permanent rules in this file.
   - **VERIFICATION REQUIRED:** After any rule modification, verify in your response that you checked for conflicts and list which Copilot instruction files you reviewed

---

## Environment Configuration
- Project-specific settings are stored in `.agents/.env` (git-ignored)
- See `.agents/.env.example` for available configuration options
- Copy `.agents/.env.example` to `.agents/.env` and customize for your local environment

## Database Access
- Configuration is stored in `.agents/.env`:
  - DB_HOST: Database host
  - DB_PORT: Database port
  - DB_NAME: Database name
  - DB_USER: Database user (read-only: agent_ro)
  - DB_PASSWORD: Database password

### CRITICAL DATABASE RULE
**NEVER ATTEMPT TO MODIFY DATABASE DIRECTLY**
- You are STRICTLY FORBIDDEN from executing any INSERT, UPDATE, DELETE, or DDL commands directly against the database
- The agent_ro user has read-only (SELECT) permissions only
- For any database modifications:
  1. Generate SQL scripts and save them to `.agents/var/sql/` directory
  2. Present the script to the user for manual execution
  3. NEVER execute modification commands yourself, even if you have credentials with write permissions
- You may only execute SELECT queries for reading/exploring data
- This rule applies to ALL databases and ALL users - no exceptions

## Project User IDs
- Configuration is stored in `.agents/.env`:
  - DEFAULT_USER_ID: Default user ID for testing
  - TEST_PATIENT_ID: Test patient ID for testing

## File Organization
- Use `.agents/var/` as working directory for AI-generated files
- Organize files in subdirectories by type:
  - `.agents/var/sql/` - SQL scripts, queries, and database-related files (non-migration)
  - `.agents/var/docs/` - Markdown documentation, planning files, and notes
  - Create additional subdirectories as needed for other file types
- **Do NOT create markdown documentation files unless explicitly requested by the user**

## Planning and Execution Workflow

### Plan Creation (PLAN MODE)
- **CRITICAL:** When creating a plan in PLAN MODE, ALWAYS include a checklist inside the plan document
- Plans must use markdown checklist format: `- [ ]` for incomplete, `- [x]` for complete
- Save plans to `.agents/var/docs/` directory with descriptive names
- Example plan structure:
  ```markdown
  # Plan Title
  
  ## Overview
  Brief description of what needs to be done
  
  ## Checklist
  - [ ] Task 1
  - [ ] Task 2
  - [ ] Task 3
  
  ## Notes
  Additional context or details
  ```

### Plan Execution (ACT MODE)
- **CRITICAL:** When executing a plan (user says "execute plan" or "execute this plan"):
  1. Read the plan document to get the checklist
  2. Use the checklist from the plan as your `task_progress` parameter
  3. Update the checklist in `task_progress` as you complete each step
  4. The checklist helps track what's done and what's remaining
- **DO NOT** create a new checklist - use the one from the plan document
- Update checklist items as work progresses using the `task_progress` parameter in tool calls

## Migration Files

### Doctrine PHP Migrations
- **Class Name Format**: `Version_YYYYMMDD_XX` (e.g., `Version_20250118_02`)
  - `YYYYMMDD`: Today's date in format YYYYMMDD
  - `XX`: Ordinal number (01, 02, 03, etc.) - increment if multiple migrations on same day
- **File Name Format**: `Version_YYYYMMDD_XX.php`
- **Location**: `migrations/YYYY/` directory (e.g., `migrations/2025/`)
- **Namespace**: `DoctrineMigrations`
- Migration history is tracked in `public.doctrine_migration_versions` table

### SQL Migrations (Legacy)
- Format: `YYYYMMDD<ordinal number XX>-<current branch name>.sql`
- Location: `sql/` directory
- **IMPORTANT**: If current branch name does not start with `RSS-`, confirm with user before creating migration
- Non-migration SQL scripts go in `.agents/var/sql/`

### Migration Structure Guidelines
- **IMPORTANT:** All SQL migration structure rules (DO/END blocks, ownership, grants, database type handling) are defined in `.github/copilot/instructions/` - refer to those instructions
- Migration-specific rules below cover only file naming and location conventions

## Error Handling
- Throw exceptions for critical errors
- Return empty arrays/null for non-critical failures

## Security
- Always use parameterized queries (`:parameter` syntax)
- Validate user input before processing

## Code Style
- **IMPORTANT:** Always follow the coding instructions defined in `.github/copilot/instructions/` folder when working with code
- All coding standards, security rules, and style guidelines are defined in Copilot instructions
- **CRITICAL:** All PHP code must be PHP CodeSniffer (phpcs) compliant
  - **phpcs scope:** Only checks files in `bin/`, `config/`, `public/`, and `src/` directories (see `phpcs.xml`)
  - **DO NOT** run phpcs on files outside these directories (e.g., `migrations/`, `tests/`, `sql/`)
  - Run `vendor/bin/phpcs` without arguments to check all files in scope
  - Run `vendor/bin/phpcbf` to auto-fix issues when possible
  - Verify compliance before completing any task involving PHP code
  - Project uses PSR12 standard with custom rules defined in `phpcs.xml`
  - Migration class names with underscores (e.g., `Version2025121601_RSS_185`) are acceptable per Doctrine conventions

## API Data Serialization
- **CRITICAL:** All incoming and outgoing data MUST go through Symfony's serializer/deserializer
- **Incoming Data (Requests):**
  - Use `SerializerInterface::deserialize()` to convert JSON to entities/DTOs
  - Never manually construct entities from request arrays
- **Outgoing Data (Responses):**
  - Use `SerializerInterface::serialize()` or `AbstractController::json()` to convert entities to JSON
  - Never manually construct response arrays from entity getters
  - Exception: Simple error responses (e.g., validation errors) can be manual arrays
- **Rationale:** 
  - Ensures consistent data transformation
  - Leverages Symfony's normalization/denormalization pipeline
  - Respects serialization groups and context
  - Maintains compatibility with API Platform

## Copilot Instructions Management
- **CRITICAL:** When asked to modify "copilot instructions", ONLY edit files in `.github/copilot/instructions/` directory
- **NEVER** edit `.github/copilot-instructions.md` directly - it is auto-generated
- After modifying instruction files, always run: `bash .github/scripts/generate-copilot-instructions.sh`
- The generation script combines all `.md` files from `.github/copilot/instructions/` into `.github/copilot-instructions.md`
- Commit both the source instruction file(s) and the generated `copilot-instructions.md`

---

**Rule Verification (API Data Serialization):**
- Checked: `.github/copilot/instructions/` directory (no files found)
- No conflicts detected
- This rule is compatible with Symfony best practices
