<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands;

use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainCore\Compilation\Tools\BashTool;
use BrainNode\Mcp\VectorMemoryMcp;

/**
 * Universal safety, quality, and taxonomy methods shared across Task and Do command traits.
 * Single source of truth for: tag taxonomy, secrets/PII protection, failure policy,
 * documentation rules, code quality rules, destructive git protection, and auto-approval.
 *
 * Hierarchy:
 *   InputCaptureTrait          <- base input capture
 *       |
 *   SharedCommandTrait         <- universal safety + quality methods + tag constants
 *       |           |
 *   TaskCommand     DoCommand  <- domain-specific methods
 *   CommonTrait     CommonTrait
 *
 * Used by: TaskCommandCommonTrait, DoCommandCommonTrait
 */
trait SharedCommandTrait
{
    use InputCaptureTrait;

    // =========================================================================
    // TAG TAXONOMY — INDIVIDUAL CONSTANTS (SINGLE SOURCE OF TRUTH)
    // =========================================================================

    // --- Task Tags: Workflow (pipeline stage) ---
    protected const TAG_DECOMPOSED = 'decomposed';
    protected const TAG_VALIDATION_FIX = 'validation-fix';
    protected const TAG_BLOCKED = 'blocked';
    protected const TAG_STUCK = 'stuck';
    protected const TAG_NEEDS_RESEARCH = 'needs-research';
    protected const TAG_LIGHT_VALIDATION = 'light-validation';
    protected const TAG_PARALLEL_SAFE = 'parallel-safe';
    protected const TAG_ATOMIC = 'atomic';
    protected const TAG_MANUAL_ONLY = 'manual-only';
    protected const TAG_REGRESSION = 'regression';

    // --- Task Tags: Type (work kind) ---
    protected const TAG_FEATURE = 'feature';
    protected const TAG_BUGFIX = 'bugfix';
    protected const TAG_REFACTOR = 'refactor';
    protected const TAG_RESEARCH = 'research';
    protected const TAG_DOCS = 'docs';
    protected const TAG_TEST = 'test';
    protected const TAG_CHORE = 'chore';
    protected const TAG_SPIKE = 'spike';
    protected const TAG_HOTFIX = 'hotfix';

    // --- Task Tags: Domain (area) ---
    protected const TAG_BACKEND = 'backend';
    protected const TAG_FRONTEND = 'frontend';
    protected const TAG_DATABASE = 'database';
    protected const TAG_API = 'api';
    protected const TAG_AUTH = 'auth';
    protected const TAG_UI = 'ui';
    protected const TAG_CONFIG = 'config';
    protected const TAG_INFRA = 'infra';
    protected const TAG_CI_CD = 'ci-cd';
    protected const TAG_MIGRATION = 'migration';

    // --- Memory Tags: Content (kind) ---
    protected const MTAG_PATTERN = 'pattern';
    protected const MTAG_SOLUTION = 'solution';
    protected const MTAG_FAILURE = 'failure';
    protected const MTAG_DECISION = 'decision';
    protected const MTAG_INSIGHT = 'insight';
    protected const MTAG_WORKAROUND = 'workaround';
    protected const MTAG_DEPRECATED = 'deprecated';

    // --- Memory Tags: Scope (breadth) ---
    protected const MTAG_PROJECT_WIDE = 'project-wide';
    protected const MTAG_MODULE_SPECIFIC = 'module-specific';
    protected const MTAG_TEMPORARY = 'temporary';
    protected const MTAG_REUSABLE = 'reusable';

    // --- Memory Categories ---
    protected const CAT_CODE_SOLUTION = 'code-solution';
    protected const CAT_BUG_FIX = 'bug-fix';
    protected const CAT_ARCHITECTURE = 'architecture';
    protected const CAT_LEARNING = 'learning';
    protected const CAT_DEBUGGING = 'debugging';
    protected const CAT_PERFORMANCE = 'performance';
    protected const CAT_SECURITY = 'security';
    protected const CAT_PROJECT_CONTEXT = 'project-context';

    // =========================================================================
    // TAG TAXONOMY — GROUPED ARRAYS (for rules/guidelines generation)
    // =========================================================================

    /** @var string[] Workflow stage tags (where in pipeline) */
    protected const TASK_TAGS_WORKFLOW = [
        self::TAG_DECOMPOSED,
        self::TAG_VALIDATION_FIX,
        self::TAG_BLOCKED,
        self::TAG_STUCK,
        self::TAG_NEEDS_RESEARCH,
        self::TAG_LIGHT_VALIDATION,
        self::TAG_PARALLEL_SAFE,
        self::TAG_ATOMIC,
        self::TAG_MANUAL_ONLY,
        self::TAG_REGRESSION,
    ];

    /** @var string[] Task type tags (what kind of work) */
    protected const TASK_TAGS_TYPE = [
        self::TAG_FEATURE,
        self::TAG_BUGFIX,
        self::TAG_REFACTOR,
        self::TAG_RESEARCH,
        self::TAG_DOCS,
        self::TAG_TEST,
        self::TAG_CHORE,
        self::TAG_SPIKE,
        self::TAG_HOTFIX,
    ];

    /** @var string[] Domain tags (what area) */
    protected const TASK_TAGS_DOMAIN = [
        self::TAG_BACKEND,
        self::TAG_FRONTEND,
        self::TAG_DATABASE,
        self::TAG_API,
        self::TAG_AUTH,
        self::TAG_UI,
        self::TAG_CONFIG,
        self::TAG_INFRA,
        self::TAG_CI_CD,
        self::TAG_MIGRATION,
    ];

    /** @var string[] Memory content type tags */
    protected const MEMORY_TAGS_CONTENT = [
        self::MTAG_PATTERN,
        self::MTAG_SOLUTION,
        self::MTAG_FAILURE,
        self::MTAG_DECISION,
        self::MTAG_INSIGHT,
        self::MTAG_WORKAROUND,
        self::MTAG_DEPRECATED,
    ];

    /** @var string[] Memory scope tags */
    protected const MEMORY_TAGS_SCOPE = [
        self::MTAG_PROJECT_WIDE,
        self::MTAG_MODULE_SPECIFIC,
        self::MTAG_TEMPORARY,
        self::MTAG_REUSABLE,
    ];

    /** @var string[] Memory categories */
    protected const MEMORY_CATEGORIES = [
        self::CAT_CODE_SOLUTION,
        self::CAT_BUG_FIX,
        self::CAT_ARCHITECTURE,
        self::CAT_LEARNING,
        self::CAT_DEBUGGING,
        self::CAT_PERFORMANCE,
        self::CAT_SECURITY,
        self::CAT_PROJECT_CONTEXT,
    ];

    // =========================================================================
    // TAG TAXONOMY RULES (COMPILE-TIME ENFORCEMENT)
    // =========================================================================

    /**
     * Define tag taxonomy rules and guidelines.
     * Ensures all task tags, memory tags, and memory categories
     * use ONLY predefined values from constants above.
     * Used by: ALL task and do commands that create tasks or store memories.
     */
    protected function defineTagTaxonomyRules(): void
    {
        $taskTagsAll = implode(', ', array_merge(
            self::TASK_TAGS_WORKFLOW,
            self::TASK_TAGS_TYPE,
            self::TASK_TAGS_DOMAIN,
        ));

        $memoryTagsAll = implode(', ', array_merge(
            self::MEMORY_TAGS_CONTENT,
            self::MEMORY_TAGS_SCOPE,
        ));

        $this->rule('task-tags-predefined-only')->critical()
            ->text('Task tags MUST use ONLY predefined values. FORBIDDEN: inventing new tags, synonyms, variations. Allowed: '.$taskTagsAll.'.')
            ->why('Ad-hoc tags cause explosion ("user-auth", "authentication", "auth" = same thing, search finds none). Predefined list = consistent search.')
            ->onViolation('Replace with closest predefined match. No match = skip tag, put context in content.');

        $this->rule('memory-tags-predefined-only')->critical()
            ->text('Memory tags MUST use ONLY predefined values. Allowed: '.$memoryTagsAll.'.')
            ->why('Unknown tags = unsearchable memories. Predefined = discoverable.')
            ->onViolation('Replace with closest predefined match.');

        $this->rule('memory-categories-predefined-only')->critical()
            ->text('Memory category MUST be one of: '.implode(', ', self::MEMORY_CATEGORIES).'. FORBIDDEN: "other", "general", "misc", or unlisted.')
            ->why('"other" is garbage nobody searches. Every memory needs meaningful category.')
            ->onViolation('Choose most relevant from predefined list.');

        $this->guideline('task-tag-selection')
            ->goal('Select 1-4 tags per task. Combine dimensions for precision.')
            ->text('WORKFLOW (pipeline stage): '.implode(', ', self::TASK_TAGS_WORKFLOW))
            ->text('TYPE (work kind): '.implode(', ', self::TASK_TAGS_TYPE))
            ->text('DOMAIN (area): '.implode(', ', self::TASK_TAGS_DOMAIN))
            ->text('Formula: 1 TYPE + 1 DOMAIN + 0-2 WORKFLOW. Example: ["feature", "api"] or ["bugfix", "auth", "validation-fix"]. Max 4 tags.');

        $this->guideline('memory-tag-selection')
            ->goal('Select 1-3 tags per memory. Combine dimensions.')
            ->text('CONTENT (kind): '.implode(', ', self::MEMORY_TAGS_CONTENT))
            ->text('SCOPE (breadth): '.implode(', ', self::MEMORY_TAGS_SCOPE))
            ->text('Formula: 1 CONTENT + 0-1 SCOPE. Example: ["solution", "reusable"] or ["failure", "module-specific"]. Max 3 tags.');
    }

    // =========================================================================
    // DOCUMENTATION IS LAW (CRITICAL FOUNDATION)
    // =========================================================================

    /**
     * Define documentation-is-law rules.
     * These rules ensure agents follow documentation exactly without inventing alternatives.
     * Used by: ALL task execution, validation, and do commands.
     */
    protected function defineDocumentationIsLawRules(): void
    {
        $this->rule('docs-are-law')->critical()
            ->text('Documentation is the SINGLE SOURCE OF TRUTH. If docs exist for task - FOLLOW THEM EXACTLY. No deviations, no "alternatives", no "options" that docs don\'t mention.')
            ->why('User wrote docs for a reason. Asking about non-existent alternatives wastes time and shows you didn\'t read the docs.')
            ->onViolation('Re-read documentation. Execute ONLY what docs specify.');

        $this->rule('no-phantom-options')->critical()
            ->text('FORBIDDEN: Asking "keep as is / rewrite / both?" when docs specify ONE approach. If docs say HOW to do it - do it. Don\'t invent alternatives.')
            ->why('Docs are the holy grail. Phantom options confuse user and delay work.')
            ->onViolation('Check docs again. If docs are clear - execute. If genuinely ambiguous - ask about THAT ambiguity, not made-up options.');

        $this->rule('partial-work-continue')->critical()
            ->text('Partial implementation exists? Read DOCS first, understand FULL spec. Continue from where it stopped ACCORDING TO DOCS. Never ask "keep partial or rewrite" - docs define target state.')
            ->why('Partial work means someone started following docs. Continue following docs, not inventing alternatives.')
            ->onViolation('Read docs → understand target state → implement remaining parts per docs.');

        $this->rule('docs-over-existing-code')->high()
            ->text('Conflict between docs and existing code? DOCS WIN. Existing code may be: WIP, placeholder, wrong, outdated. Docs define WHAT SHOULD BE.')
            ->why('Code is implementation, docs are specification. Spec > current impl.');

        $this->rule('context-priority-chain')->high()
            ->text('Conflict resolution priority: documentation > existing code > vector memory > assumptions. When sources disagree, higher-priority source wins. Documentation defines WHAT SHOULD BE. Code shows WHAT IS NOW. Memory shows WHAT WAS BEFORE. Assumptions are last resort when all sources are absent.')
            ->why('Multiple context sources may contradict each other. Without explicit priority chain, agents pick whichever they loaded first. Clear hierarchy eliminates ambiguity in conflict resolution.');

        $this->rule('aggressive-docs-search')->critical()
            ->text('NEVER search docs with single exact query. Generate 3-5 keyword variations: 1) split CamelCase (FocusModeTest → "FocusMode", "Focus Mode", "Focus"), 2) remove technical suffixes (Test, Controller, Service, Repository, Command, Handler, Provider), 3) extract domain words, 4) try singular/plural. Search until found OR 3+ variations tried.')
            ->why('Docs may be named differently than code. "FocusModeTest" code → "Focus Mode" doc. Single exact search = missed docs = wrong decisions.')
            ->onViolation('Generate keyword variations. Search each. Only conclude "no docs" after 3+ failed searches.');

        $this->defineAggressiveDocsSearchGuideline();
    }

    /**
     * Define aggressive documentation search guideline.
     * Ensures multiple search attempts with keyword variations before concluding no docs exist.
     * Used by: ALL task execution, validation, and do commands.
     */
    protected function defineAggressiveDocsSearchGuideline(): void
    {
        $this->guideline('aggressive-docs-search')
            ->goal('Find documentation even if named differently than task/code')
            ->example()
            ->phase('Generate keyword variations from task title/content:')
            ->phase('  1. Original: "FocusModeTest" → search "FocusModeTest"')
            ->phase('  2. Split CamelCase: "FocusModeTest" → search "FocusMode", "Focus Mode"')
            ->phase('  3. Remove suffix: "FocusModeTest" → search "Focus" (remove Mode, Test)')
            ->phase('  4. Domain words: extract meaningful nouns → search each')
            ->phase('  5. Parent context: if task has parent → include parent title keywords')
            ->phase('Common suffixes to STRIP: Test, Tests, Controller, Service, Repository, Command, Handler, Provider, Factory, Manager, Helper, Validator, Processor')
            ->phase('Search ORDER: most specific → most general. STOP when found.')
            ->phase('Minimum 3 search attempts before concluding "no documentation".')
            ->phase('WRONG: brain docs "UserAuthenticationServiceTest" → not found → done')
            ->phase('RIGHT: brain docs "UserAuthenticationServiceTest" → not found → brain docs "UserAuthentication" → not found → brain docs "Authentication" → FOUND!');
    }

    // =========================================================================
    // SECRETS & PII PROTECTION (NO EXFILTRATION)
    // =========================================================================

    /**
     * Define secrets and PII protection rules.
     * Prevents exfiltration of sensitive data via chat output, task comments,
     * or vector memory storage.
     * Used by: ALL task and do commands.
     */
    protected function defineSecretsPiiProtectionRules(): void
    {
        $this->rule('no-secret-exfiltration')->critical()
            ->text('NEVER output sensitive data to chat/response: .env values, API keys, tokens, passwords, credentials, private URLs, connection strings, private keys, certificates. When reading config/.env for CONTEXT: extract key NAMES and STRUCTURE only, never raw values. If user asks to show .env or config with secrets: show key names, mask values as "***". If error output contains secrets: redact before displaying.')
            ->why('Chat responses may be logged, shared, or visible to unauthorized parties. Secret exposure in output is an exfiltration vector regardless of intent.')
            ->onViolation('REDACT immediately. Replace value with "***" or "[REDACTED]". Show key names only.');

        $this->rule('no-secrets-in-storage')->critical()
            ->text('NEVER store secrets, credentials, tokens, passwords, API keys, PII, or connection strings in task comments (task_update comment) or vector memory (store_memory content). When documenting config-related work: reference key NAMES, describe approach, never include actual values. If error log contains secrets: strip sensitive values before storing. Acceptable: "Updated DB_HOST in .env", "Rotated API_KEY for service X". Forbidden: "Set DB_HOST=192.168.1.5", "API_KEY=sk-abc123...".')
            ->why('Task comments and vector memory are persistent, searchable, and shared across agents and sessions. Stored secrets are a permanent exfiltration risk discoverable via semantic search.')
            ->onViolation('Review content before store_memory/task_update. Strip all literal secret values. Keep only key names and descriptions.');
    }

    // =========================================================================
    // FAILURE AWARENESS (PREVENT REPEATING MISTAKES)
    // =========================================================================

    /**
     * Define failure awareness rules.
     * Ensures commands check for known failures and sibling task history
     * before starting work, preventing repetition of failed approaches.
     * Used by: ALL task and do execution commands.
     */
    protected function defineFailureAwarenessRules(): void
    {
        $this->rule('failure-history-mandatory')->critical()
            ->text('BEFORE starting work: search memory category "debugging" for KNOWN FAILURES related to this task/problem. DO NOT attempt solutions that already failed.')
            ->why('Repeating failed solutions wastes time. Memory contains "this does NOT work" knowledge.')
            ->onViolation('Search debugging memories FIRST. Block known-failed approaches.');

        $this->rule('sibling-task-check')->high()
            ->text('BEFORE starting work: fetch sibling tasks (same parent_id, status=completed/stopped). Check comments for what was tried and failed.')
            ->why('Previous attempts on same problem contain valuable "what not to do" information.')
            ->onViolation('task_list with parent_id, extract failure patterns from comments.');
    }

    // =========================================================================
    // FAILURE POLICY STATE MACHINE (UNIVERSAL BASELINE)
    // =========================================================================

    /**
     * Define universal failure policy rules.
     * Provides consistent, predictable behavior for the 3 most common failure scenarios:
     * TOOL_ERROR, MISSING_DOCS, AMBIGUOUS_SPEC.
     * Used by: ALL task and do commands.
     */
    protected function defineFailurePolicyRules(): void
    {
        $this->rule('failure-policy-tool-error')->critical()
            ->text('TOOL ERROR / MCP FAILURE: 1) Retry ONCE with same parameters. 2) Still fails → STOP current step. 3) Store failure to memory (category: "'.self::CAT_DEBUGGING.'", tags: ["'.self::MTAG_FAILURE.'"]). 4) Update task comment: "BLOCKED: {tool} failed after retry. Error: {msg}", append_comment: true. 5) -y mode: set status "pending" (return to queue for retry), abort current workflow. Interactive: ask user "Tool failed. Retry/Skip/Abort?". NEVER set "stopped" on failure — "stopped" = permanently cancelled.')
            ->why('Consistent tool failure handling across all commands. One retry catches transient issues. Failed task returns to pending queue — it is NOT cancelled, just needs another attempt or manual intervention.')
            ->onViolation('Follow 5-step sequence. Max 1 retry for same tool call. Always store failure to memory. Status → pending, NEVER stopped.');

        $this->rule('failure-policy-missing-docs')->high()
            ->text('MISSING DOCS: 1) Apply aggressive-docs-search (3+ keyword variations). 2) All variations exhausted → conclude "no docs". 3) Proceed using: task.content (primary spec) + vector memory context + parent task context. 4) Log in task comment: "No documentation found after {N} search attempts. Proceeding with task.content.", append_comment: true. NOT a blocker — absence of docs is information, not failure.')
            ->why('Missing docs must not block execution. task.content is the minimum viable specification. Blocking on missing docs causes pipeline stalls for tasks that never had docs.')
            ->onViolation('Never block on missing docs. Search aggressively, then proceed with available context.');

        $this->rule('failure-policy-ambiguous-spec')->high()
            ->text('AMBIGUOUS SPEC: 1) Identify SPECIFIC ambiguity (not "task is unclear" but "field X: type A or B?"). 2) -y mode: choose conservative/safe interpretation, log decision in task comment: "DECISION: interpreted {X} as {Y} because {reason}", append_comment: true. 3) Interactive: ask ONE targeted question about the SPECIFIC gap. 4) After 1 clarification → proceed. NEVER ask open-ended "what did you mean?" or multiple follow-ups.')
            ->why('Ambiguity paralysis wastes more time than conservative interpretation. One precise question is enough — if user wanted detailed spec, they would have written docs.')
            ->onViolation('Identify specific gap. One question or auto-decide. Proceed.');
    }

    // =========================================================================
    // NO DESTRUCTIVE GIT (PROTECT PARALLEL AGENTS & MEMORY)
    // =========================================================================

    /**
     * Define no-destructive-git rules.
     * Prohibits git commands that modify working tree state.
     * memory/ contains SQLite databases (vector memory + tasks) tracked in git.
     * Used by: ALL task and do execution, validation, and delegation commands.
     */
    protected function defineNoDestructiveGitRules(): void
    {
        $this->rule('no-destructive-git')->critical()
            ->text('FORBIDDEN: git checkout, git restore, git stash, git reset, git clean — and ANY command that modifies git working tree state. These destroy uncommitted work from parallel agents, user WIP, and memory/ SQLite databases (vector memory + tasks). Rollback = Read original content + Write/Edit back. Git is READ-ONLY: status, diff, log, blame only.')
            ->why('memory/ folder contains project SQLite databases tracked in git. git checkout/stash/reset reverts these databases, destroying ALL tasks and memories. Parallel agents have uncommitted changes — any working tree modification wipes their work. Unrecoverable data loss.')
            ->onViolation('ABORT git command. Use Read to get original content, Write/Edit to restore specific files. Never touch git working tree state.');

        $this->rule('no-destructive-git-in-agents')->critical()
            ->text('When delegating to agents: ALWAYS include in prompt: "FORBIDDEN: git checkout, git restore, git stash, git reset, git clean. Rollback = Read + Write. Git is READ-ONLY."')
            ->why('Sub-agents do not inherit parent rules. Without explicit prohibition, agents will use git for rollback and destroy parallel work.')
            ->onViolation('Add git prohibition to agent prompt before delegation.');

        $this->rule('memory-folder-sacred')->critical()
            ->text('memory/ folder contains SQLite databases (vector memory + tasks). SACRED — protect at ALL times. NEVER git checkout/restore/reset/clean memory/ — these DESTROY all project knowledge irreversibly. In PARALLEL CONTEXT: use "git add {specific_files}" (task-scope only) — memory/ excluded implicitly because it is not in task files. In NON-PARALLEL context: "git add -A" is safe and DESIRED — includes memory/ for full state checkpoint preserving knowledge base alongside code.')
            ->why('memory/ is the project persistent brain. Destructive git commands on memory/ = total knowledge loss. In parallel mode, concurrent SQLite writes + git add -A = binary merge conflicts and staged half-done sibling work. In sequential mode, committing memory/ preserves full project state for safe revert.')
            ->onViolation('NEVER destructive git on memory/. Parallel: git add specific files only (memory/ not in scope). Non-parallel: git add -A (full checkpoint with memory/).');
    }

    // =========================================================================
    // AUTO-APPROVAL RULES
    // =========================================================================

    /**
     * Define auto-approval and workflow atomicity rules.
     * When -y flag is active, workflow executes to completion without stops.
     * When -y is absent, workflow pauses at major decision points.
     * Used by: ALL task and do command includes.
     */
    protected function defineAutoApprovalRules(): void
    {
        $this->rule('auto-approve-mode')->critical()
            ->text('$HAS_AUTO_APPROVE = true → FULL AUTONOMY. Skip ALL approval gates, questions, strategy decisions, ambiguity resolution. On ANY decision fork: choose conservative/non-blocking option automatically. NEVER use AskUserQuestion or similar interactive tools. Workflow MUST execute to completion: all phases → final status update → git checkpoint. No intermediate stops, no "show results and wait for acknowledgment."')
            ->why('User explicitly chose autonomous mode via -y flag. Every question breaks flow, risks hook-triggered terminal closure mid-pause, and defeats the purpose of automation.')
            ->onViolation('Remove the question. Choose conservative option. Log decision in task comment. Continue to next phase without stopping.');

        $this->rule('interactive-mode')->high()
            ->text('$HAS_AUTO_APPROVE = false → INTERACTIVE. Present plan → wait for approval → execute. Ask before: major architectural decisions, multiple valid approaches, critical failures requiring user judgment.')
            ->why('User wants control over significant decisions. Present options clearly, wait for explicit choice.');

        $this->rule('workflow-atomicity')->critical()
            ->text('In auto-approve mode, workflow is ATOMIC: execute ALL phases without intermediate stops until final status is set (completed/validated/tested). On error: revert status to "pending" with error details in comment (task returns to queue), NEVER ask user what to do. NEVER set "stopped" — that means permanently cancelled. Update task comment at each major milestone so interrupted workflow has recoverable state.')
            ->why('Hook-triggered terminal closure during a pause leaves task in limbo with no recoverable state. Atomic execution minimizes pause windows. Milestone comments enable session recovery without re-running completed phases. Failed tasks return to pending — they are not cancelled, just need another attempt.')
            ->onViolation('If paused in auto-approve mode: immediately resume. If error: set status=pending, add error to comment, abort gracefully.');
    }

    /**
     * Define mandatory-user-approval rule (create/decompose style).
     * Used by: TaskCreateInclude, TaskDecomposeInclude, and Do commands.
     */
    protected function defineMandatoryUserApprovalRule(): void
    {
        $this->rule('mandatory-user-approval')->critical()
            ->text('EVERY operation MUST have explicit user approval BEFORE execution. Present plan → WAIT for approval → Execute. NO auto-execution. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve.')
            ->why('User maintains control. No surprises. Flag -y enables automated execution.')
            ->onViolation('STOP. Wait for explicit user approval (unless $HAS_AUTO_APPROVE is true).');
    }

    // =========================================================================
    // CODEBASE PATTERN REUSE (CONSISTENCY & DRY)
    // =========================================================================

    /**
     * Define codebase pattern reuse rule.
     * Ensures agents search for similar existing implementations before writing new code.
     * Prevents reinventing the wheel and maintains codebase consistency.
     * Used by: ALL task and do execution commands.
     */
    protected function defineCodebasePatternReuseRule(): void
    {
        $this->rule('codebase-pattern-reuse')->critical()
            ->text('BEFORE implementing: search codebase for similar/analogous implementations. Grep for: similar class names, method signatures, trait usage, helper utilities. Found → REUSE approach, follow same patterns, extend existing code. Not found → proceed independently. NEVER reinvent what already exists in the project.')
            ->why('Codebase consistency > personal style. Duplicate implementations create maintenance burden, inconsistency, and confusion. Existing patterns are battle-tested.')
            ->onViolation('STOP. Search codebase for analogous code. Found → study and follow the pattern. Only then proceed.');
    }

    /**
     * Define codebase pattern reuse workflow guideline.
     * Step-by-step search procedure for finding and reusing existing implementations.
     * Used by: ALL task and do execution commands.
     */
    protected function defineCodebasePatternReuseGuideline(): void
    {
        $this->guideline('codebase-pattern-reuse')
            ->goal('Find and reuse existing patterns before implementing anything new')
            ->example()
            ->phase('1. IDENTIFY: From task extract: class type, feature domain, architectural pattern')
            ->phase('2. SEARCH SIMILAR: Grep for analogous class names, method names, trait usage')
            ->phase('   Creating new Service → Grep *Service.php → Read → extract pattern')
            ->phase('   Adding validation → Grep existing validation → follow same approach')
            ->phase('   New API endpoint → Find existing endpoints → follow same structure')
            ->phase('3. SEARCH HELPERS: Grep for existing utilities, traits, base classes to reuse')
            ->phase('4. EVALUATE: ' . Store::as('EXISTING_PATTERNS', '{files, approach, utilities, base classes, conventions}'))
            ->phase('5. APPLY: Use $EXISTING_PATTERNS as blueprint. Follow conventions, extend helpers, reuse base classes.')
            ->phase('6. NOT FOUND: Proceed independently. Still follow project conventions from other code.');
    }

    // =========================================================================
    // IMPACT RADIUS ANALYSIS (REVERSE DEPENDENCY)
    // =========================================================================

    /**
     * Define impact radius analysis rule.
     * Ensures proactive reverse dependency check BEFORE editing files.
     * Prevents cascade failures from changing code that others depend on.
     * Used by: ALL task and do execution commands.
     */
    protected function defineImpactRadiusAnalysisRule(): void
    {
        $this->rule('impact-radius-analysis')->critical()
            ->text('BEFORE editing any file: check WHO DEPENDS on it. Grep for imports/use/require/extends/implements of target file. Dependents found → plan changes to not break them. Changing public method/function signature → update ALL callers or flag as breaking change.')
            ->why('Changing code without knowing its consumers causes cascade failures. Proactive impact analysis prevents breaking downstream code.')
            ->onViolation('STOP. Grep for reverse dependencies of target file. Assess impact BEFORE editing.');
    }

    /**
     * Define impact radius analysis workflow guideline.
     * Step-by-step procedure for assessing change blast radius.
     * Used by: ALL task and do execution commands.
     */
    protected function defineImpactRadiusAnalysisGuideline(): void
    {
        $this->guideline('impact-radius-analysis')
            ->goal('Understand blast radius before making changes')
            ->example()
            ->phase('1. For EACH file in change plan: Grep for imports/use/require/extends/implements referencing it')
            ->phase('2. Map dependents: {file → [consumers]}')
            ->phase('3. Classify: NONE (internal-only change) | LOW (private/unused externally) | MEDIUM (few consumers) | HIGH (widely used)')
            ->phase('4. HIGH impact → review all callers, ensure signature compatibility, include dependents in plan')
            ->phase('5. ' . Store::as('DEPENDENTS_MAP', '{file → [consumers], impact_level}'))
            ->phase('6. Changing interface/trait/abstract/base class → ALL implementors/users MUST be checked');
    }

    // =========================================================================
    // LOGIC & EDGE CASE VERIFICATION
    // =========================================================================

    /**
     * Define logic and edge case verification rule.
     * Ensures explicit logic correctness review after implementation.
     * Used by: ALL task and do execution commands.
     */
    protected function defineLogicEdgeCaseVerificationRule(): void
    {
        $this->rule('logic-edge-case-verification')->high()
            ->text('After implementation: explicitly verify logic correctness for each changed function/method. Check: null/empty inputs, boundary values (0, -1, MAX, empty collection), off-by-one errors, error/exception paths, type coercion edge cases, concurrent access if applicable. Ask: "what happens if input is null? empty? maximum?"')
            ->why('AI-generated code has 75% more logic bugs than human code. Syntax and linter pass but logic fails silently. Most missed category in code reviews.')
            ->onViolation('Review each changed function: what happens with null? empty? boundary? error path? Fix before proceeding.');
    }

    // =========================================================================
    // PERFORMANCE AWARENESS
    // =========================================================================

    /**
     * Define performance awareness rule.
     * Prevents common performance anti-patterns during coding.
     * Used by: ALL task and do execution commands.
     */
    protected function definePerformanceAwarenessRule(): void
    {
        $this->rule('performance-awareness')->high()
            ->text('During implementation: avoid known performance anti-patterns. Check for: nested loops over data (O(n²)), query-per-item patterns (N+1), I/O operations inside loops, loading entire datasets when subset needed, blocking operations where async possible, missing pagination for large collections, unnecessary serialization/deserialization.')
            ->why('AI-generated code has 8x more performance issues than human code, especially I/O patterns. Catching during coding is cheaper than fixing after validation.')
            ->onViolation('Review loops: is there a query/I/O inside? Can it be batched? Is the algorithm optimal for expected data size?');
    }

    // =========================================================================
    // CODE HALLUCINATION PREVENTION
    // =========================================================================

    /**
     * Define code hallucination prevention rule.
     * Ensures generated code references real methods/classes/functions.
     * Used by: ALL task and do execution commands.
     */
    protected function defineCodeHallucinationPreventionRule(): void
    {
        $this->rule('code-hallucination-prevention')->critical()
            ->text('Before using any method/function/class in generated code: VERIFY it actually exists with correct signature. Read the source or use Grep to confirm. NEVER assume API exists based on naming convention. Common hallucinations: wrong method names, incorrect parameter order/count, non-existent helper functions, invented framework methods, deprecated APIs used as current.')
            ->why('AI generates plausible-looking code referencing non-existent APIs. Parses and lints OK but fails at runtime. Most dangerous because it looks correct.')
            ->onViolation('Read actual source for EVERY external method/class used. Verify name + parameter signature before writing.');
    }

    // =========================================================================
    // CLEANUP AFTER CHANGES
    // =========================================================================

    /**
     * Define cleanup after changes rule.
     * Ensures dead code and artifacts are removed after edits.
     * Used by: ALL task and do execution commands.
     */
    protected function defineCleanupAfterChangesRule(): void
    {
        $this->rule('cleanup-after-changes')->medium()
            ->text('After all edits: scan changed files for artifacts. Remove: unused imports/use/require statements, unreachable code after refactoring, orphaned helper functions no longer called, commented-out code blocks, stale TODO/FIXME without actionable context.')
            ->why('AI refactoring often leaves dead imports, orphaned functions, commented-out code. Accumulates technical debt and confuses future readers.')
            ->onViolation('Scan changed files for unused imports and unreachable code. Remove confirmed dead code.');
    }

    // =========================================================================
    // TEST COVERAGE DURING EXECUTION
    // =========================================================================

    /**
     * Define test coverage during execution rule.
     * Ensures executors write tests alongside implementation to meet validator thresholds.
     * Used by: ALL task and do execution commands.
     */
    protected function defineTestCoverageDuringExecutionRule(): void
    {
        $this->rule('test-coverage-during-execution')->critical()
            ->text('After implementation: check if changed code has test coverage. If NO tests exist for changed files → WRITE tests. If tests exist but coverage insufficient → ADD missing tests. Target thresholds (MUST match validator expectations): >=80% coverage, critical paths 100%, meaningful assertions (not just "no exception"), edge cases (null, empty, boundary). Follow existing test patterns in the project (detect framework, mirror directory structure, reuse base test classes). NEVER skip — missing tests = guaranteed fix-task from validator = wasted round-trip.')
            ->why('Validator expects >=80% coverage with edge cases. Missing tests = validator creates fix-task = another execution cycle. The executor understands context best and writes better tests than a cold-read agent later.')
            ->onViolation('BEFORE marking task complete: verify test coverage for ALL changed files. No tests = write them NOW. Insufficient coverage = add tests NOW.');
    }

    // =========================================================================
    // DOCUMENTATION DURING EXECUTION
    // =========================================================================

    /**
     * Define documentation during execution rule.
     * Ensures executors create/update .docs/ documentation inline during implementation.
     * Used by: ALL task and do execution commands.
     */
    protected function defineDocumentationDuringExecutionRule(): void
    {
        $this->rule('docs-during-execution')->high()
            ->text('After implementation: evaluate if documentation update needed. NEW feature/module/API without .docs/ entry → CREATE doc. Changed behavior with existing docs → UPDATE doc. Bugfix/refactor (same behavior) OR trivial (config, formatting, PHPDoc) → SKIP. Use brain docs to check existing. Write docs in .docs/ with YAML front matter (name, description, type, date, version) + clear markdown. Documentation = DESCRIPTION for humans, not code dump. Minimize code examples — text-first.')
            ->why('Documentation is declared "law" but executors never create it. Over time "docs are law" becomes empty rule because no docs exist. Executor understands the code best — creating docs during execution costs near zero (context already loaded). Separate doc-tasks are banned as micro-tasks.')
            ->onViolation('Before completing: run brain docs for feature keywords. New feature without docs → create .docs/{feature}.md.');

        $this->guideline('docs-during-execution')
            ->goal('Decide whether to create/update documentation after implementation')
            ->example()
            ->phase('Decision tree:')
            ->phase('  1. Task adds NEW feature, module, or public API? → CHECK docs')
            ->phase('  2. Task CHANGES BEHAVIOR of existing feature? → CHECK docs')
            ->phase('  3. Task is bugfix, refactor, or trivial change (no behavior change)? → SKIP docs')
            ->phase('CHECK: ' . BashTool::call(BrainCLI::DOCS('{feature keywords}')) . ' → docs found?')
            ->phase('  YES (docs exist) + behavior changed → READ doc, UPDATE relevant sections')
            ->phase('  NO (no docs) + new feature/module → CREATE .docs/{feature-name}.md')
            ->phase('  NO (no docs) + minor behavior change → SKIP (not every change needs docs)')
            ->phase('CREATE format (YAML front matter + markdown body):')
            ->phase('  ---')
            ->phase('  name: "Feature Name"')
            ->phase('  description: "Brief description of what this feature does"')
            ->phase('  type: "guide"  # guide | api | concept | architecture | reference')
            ->phase('  date: "' . date('Y-m-d') . '"')
            ->phase('  version: "1.0.0"')
            ->phase('  ---')
            ->phase('  Body: purpose, key concepts, usage, API/interface. Text-first, code only when cheaper than text.');
    }
}
