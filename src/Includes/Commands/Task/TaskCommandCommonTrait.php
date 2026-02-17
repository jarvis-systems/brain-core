<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Task;

use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;
use BrainNode\Mcp\VectorTaskMcp;

/**
 * Common patterns extracted from Task command includes.
 * Provides reusable rule and guideline definitions for task-related commands.
 *
 * Usage patterns identified across 9 Task command files:
 * - Input capture (RAW_INPUT, HAS_AUTO_APPROVE, CLEAN_ARGS, VECTOR_TASK_ID)
 * - Vector task loading with session recovery
 * - Auto-approval handling
 * - Completion status updates (SUCCESS/PARTIAL/FAILED)
 * - Error handling
 * - Vector memory mandatory rule
 * - Task consolidation (5-8h batches)
 */
trait TaskCommandCommonTrait
{
    // =========================================================================
    // TAG TAXONOMY — INDIVIDUAL CONSTANTS (SINGLE SOURCE OF TRUTH)
    // =========================================================================

    // --- Task Tags: Workflow (pipeline stage) ---
    protected const TAG_DECOMPOSED = 'decomposed';
    protected const TAG_VALIDATION_FIX = 'validation-fix';
    protected const TAG_BLOCKED = 'blocked';
    protected const TAG_NEEDS_RESEARCH = 'needs-research';
    protected const TAG_LIGHT_VALIDATION = 'light-validation';
    protected const TAG_PARALLEL_SAFE = 'parallel-safe';

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
        self::TAG_NEEDS_RESEARCH,
        self::TAG_LIGHT_VALIDATION,
        self::TAG_PARALLEL_SAFE,
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
     * Used by: ALL task commands that create tasks or store memories.
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
    // INPUT CAPTURE PATTERNS
    // =========================================================================

    /**
     * Define input capture guideline for commands with VECTOR_TASK_ID.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineInputCaptureGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('VECTOR_TASK_ID', '{numeric ID extracted from $CLEAN_ARGS}'));
    }

    /**
     * Define input capture guideline for commands with TASK_ID (decompose style).
     * Used by: TaskDecomposeInclude
     */
    protected function defineInputCaptureWithTaskIdGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('CLEAN_ARGS', '{$RAW_INPUT with flags removed}'))
            ->text(Store::as('TASK_ID', '{numeric ID extracted from $CLEAN_ARGS}'));
    }

    /**
     * Define input capture guideline for create command.
     * Used by: TaskCreateInclude
     */
    protected function defineInputCaptureWithDescriptionGuideline(): void
    {
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('HAS_AUTO_APPROVE', '{true if $RAW_INPUT contains "-y" or "--yes"}'))
            ->text(Store::as('TASK_DESCRIPTION', '{$RAW_INPUT with -y flag removed}'));
    }

    // =========================================================================
    // DOCUMENTATION IS LAW (CRITICAL FOUNDATION)
    // =========================================================================

    /**
     * Define documentation-is-law rules.
     * These rules ensure agents follow documentation/ТЗ exactly without inventing alternatives.
     * Used by: ALL task execution and validation commands.
     */
    protected function defineDocumentationIsLawRules(): void
    {
        $this->rule('docs-are-law')->critical()
            ->text('Documentation/ТЗ is the SINGLE SOURCE OF TRUTH. If docs exist for task - FOLLOW THEM EXACTLY. No deviations, no "alternatives", no "options" that docs don\'t mention.')
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

        $this->rule('aggressive-docs-search')->critical()
            ->text('NEVER search docs with single exact query. Generate 3-5 keyword variations: 1) split CamelCase (FocusModeTest → "FocusMode", "Focus Mode", "Focus"), 2) remove technical suffixes (Test, Controller, Service, Repository, Command, Handler, Provider), 3) extract domain words, 4) try singular/plural. Search until found OR 3+ variations tried.')
            ->why('Docs may be named differently than code. "FocusModeTest" code → "Focus Mode" doc. Single exact search = missed docs = wrong decisions.')
            ->onViolation('Generate keyword variations. Search each. Only conclude "no docs" after 3+ failed searches.');

        // Include the search guideline
        $this->defineAggressiveDocsSearchGuideline();
    }

    /**
     * Define aggressive documentation search guideline.
     * Ensures multiple search attempts with keyword variations before concluding no docs exist.
     * Used by: ALL task execution and validation commands.
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
    // IRON EXECUTION RULES (UNIVERSAL ACROSS COMMANDS)
    // =========================================================================

    /**
     * Define iron execution rules shared across all task commands.
     * These are universal behavioral constraints that prevent hallucination and verbosity.
     * Each command still defines its own context-specific 'task-get-first' rule.
     * Progress reporting is handled by defineMachineReadableProgressRule() separately.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude, TaskDecomposeInclude.
     */
    protected function defineIronExecutionRules(): void
    {
        $this->rule('no-hallucination')->critical()
            ->text('NEVER output results without ACTUALLY calling tools. You CANNOT know task status or content without REAL tool calls. Fake results = CRITICAL VIOLATION.');

        $this->rule('no-verbose')->critical()
            ->text('FORBIDDEN: Wrapping actions in verbose XML response blocks (<meta>, <synthesis>, <plan>, <analysis>) before executing. Act FIRST, explain AFTER.');
    }

    // =========================================================================
    // MACHINE-READABLE PROGRESS (STATUS/RESULT/NEXT OUTPUT CONTRACT)
    // =========================================================================

    /**
     * Define machine-readable progress format rule.
     * Standardized STATUS/RESULT/NEXT output contract for all executing commands.
     * Ensures parseable, consistent progress reporting across all task execution workflows.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineMachineReadableProgressRule(): void
    {
        $this->rule('machine-readable-progress')->high()
            ->text('ALL progress output MUST follow structured format. DURING EXECUTION: emit "STATUS: [phase_name] description" at each major workflow phase (task loaded, context gathered, agents delegated, validation running, etc.). AT COMPLETION: emit "RESULT: SUCCESS|PARTIAL|FAILED — key=value, key=value, summary" followed by "NEXT: recommended_action_or_command". No free-form progress — only STATUS/RESULT/NEXT lines. Examples: "STATUS: [loading] Task #42 loaded, mode=async, priority=high" | "STATUS: [context] 3 memories found, docs loaded" | "STATUS: [execution] 2 agents delegated" | "RESULT: SUCCESS — files=5, agents=3/3, memory=#123" | "NEXT: /task:validate #42".')
            ->why('Structured format enables UI rendering, orchestrator parsing, progress aggregation, and consistent user experience. Without it, each command reports differently — impossible to parse or automate.')
            ->onViolation('Reformat to STATUS/RESULT/NEXT structure. Replace free-form text with structured lines.');
    }

    // =========================================================================
    // FAILURE AWARENESS (PREVENT REPEATING MISTAKES)
    // =========================================================================

    /**
     * Define failure awareness rules.
     * Ensures commands check for known failures and sibling task history
     * before starting work, preventing repetition of failed approaches.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude.
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
     * This is a BASELINE — commands with more specific handling take precedence.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineFailurePolicyRules(): void
    {
        $this->rule('failure-policy-tool-error')->critical()
            ->text('TOOL ERROR / MCP FAILURE: 1) Retry ONCE with same parameters. 2) Still fails → STOP current step. 3) Store failure to memory (category: "'.self::CAT_DEBUGGING.'", tags: ["'.self::MTAG_FAILURE.'"]). 4) Update task comment: "BLOCKED: {tool} failed after retry. Error: {msg}", append_comment: true. 5) -y mode: set status "stopped", abort. Interactive: ask user "Tool failed. Retry/Skip/Abort?".')
            ->why('Consistent tool failure handling across all commands. One retry catches transient issues. More retries waste tokens on persistent failures.')
            ->onViolation('Follow 5-step sequence. Max 1 retry for same tool call. Always store failure to memory.');

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
    // SECRETS & PII PROTECTION (NO EXFILTRATION)
    // =========================================================================

    /**
     * Define secrets and PII protection rules.
     * Prevents exfiltration of sensitive data via chat output, task comments,
     * or vector memory storage. Complements existing code-level security rules
     * (security-no-secrets, security-secrets) which prevent hardcoding in code.
     * These rules cover the OUTPUT and STORAGE vectors.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude.
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
    // VALIDATION CORE RULES (SHARED VALIDATE/VALIDATE-SYNC)
    // =========================================================================

    /**
     * Define core validation rules shared between async and sync validation.
     * Covers: scope, completeness, cosmetic vs functional classification,
     * fix-task policy, test coverage, failure deduplication.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude.
     */
    protected function defineValidationCoreRules(): void
    {
        $this->rule('docs-are-complete-spec')->critical()
            ->text('Documentation (.docs/) = COMPLETE specification. task.content may be brief summary. ALWAYS read and validate against DOCUMENTATION if exists. Missing from docs = not a requirement. In docs but not done = MISSING.')
            ->why('task.content is often summary. Full spec lives in documentation. Validating only task.content misses requirements.')
            ->onViolation('Check DOCS_PATHS. If docs exist → read them → extract full requirements → validate against docs.');

        $this->rule('task-scope-only')->critical()
            ->text('Validate ONLY what task.content + documentation describes. Do NOT expand scope. Task says "add X" = check X exists and works. Task says "fix Y" = check Y is fixed. NOTHING MORE.');

        $this->rule('task-complete')->critical()
            ->text('ALL task requirements MUST be done. Parse task.content → list requirements → verify each. Missing = fix-task.');

        $this->rule('no-garbage')->critical()
            ->text('Garbage code in task scope = fix-task. Detect: unused imports, dead code, debug statements, commented-out blocks.');

        $this->rule('cosmetic-inline')->critical()
            ->text('Cosmetic issues = fix IMMEDIATELY inline. NO task created. Cosmetic: whitespace, typos, formatting, comments, docblocks, naming (non-breaking), import sorting.');

        $this->rule('functional-to-task')->critical()
            ->text('Functional issues = fix-task. Functional: logic bugs, security vulnerabilities, architecture violations, missing tests, broken functionality.');

        $this->rule('fix-task-blocks-validated')->critical()
            ->text('Fix-task created → status MUST be "pending", NEVER "validated". "validated" = ZERO fix-tasks. NO EXCEPTIONS.')
            ->why('MCP auto-propagation: when child task starts (status→in_progress), parent auto-reverts to pending. Setting "validated" with pending children is POINTLESS - system will reset it.')
            ->onViolation('ABORT validation. Set status="pending" BEFORE task_create. Never set "validated" if ANY fix-task exists.');

        $this->rule('test-coverage')->high()
            ->text('No test coverage = fix-task. Critical paths = 100%, other >= 80%.');

        $this->rule('slow-test-detection')->high()
            ->text('Slow tests = fix-task. Unit >500ms, integration >2s, any >5s = CRITICAL.');

        $this->rule('no-repeat-failures')->critical()
            ->text('BEFORE creating fix-task: check if proposed solution matches known failure. If memory says "X does NOT work for Y" — DO NOT create task suggesting X. Research alternative or escalate.')
            ->why('Creating fix-task with known-failed solution = guaranteed failure + wasted effort.')
            ->onViolation('Search memory for proposed fix. Match found in debugging = BLOCK task creation, suggest alternative.');
    }

    // =========================================================================
    // PARENT ID MANDATORY (HIERARCHY INTEGRITY)
    // =========================================================================

    /**
     * Define parent-id-mandatory rule.
     * Ensures all new tasks/subtasks created during a command are children
     * of the current task, maintaining hierarchy integrity.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude,
     *          TaskDecomposeInclude, TaskBrainstormInclude.
     *
     * @param string $variableName Variable name holding the task ID (default: 'VECTOR_TASK_ID')
     */
    protected function defineParentIdMandatoryRule(string $variableName = 'VECTOR_TASK_ID'): void
    {
        $this->rule('parent-id-mandatory')->critical()
            ->text('ALL new tasks/subtasks created MUST have parent_id = $'.$variableName.'. No orphan tasks. No exceptions.')
            ->why('Task hierarchy integrity. Orphan tasks break traceability and workflow.')
            ->onViolation('ABORT task_create if parent_id missing or != $'.$variableName.'.');
    }

    // =========================================================================
    // COMMON RULES
    // =========================================================================

    /**
     * Define vector-task-id-required rule.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $alternativeCommand Command to suggest for text-based tasks (e.g., '/do:async', '/do:validate')
     */
    protected function defineVectorTaskIdRequiredRule(string $alternativeCommand): void
    {
        $this->rule('vector-task-id-required')->critical()
            ->text('$TASK_ID MUST be a valid vector task ID reference. Valid formats: "15", "#15", "task 15", "task:15", "task-15". If not a valid task ID, abort and suggest '.$alternativeCommand.' for text-based tasks.')
            ->why('This command is exclusively for vector task execution. Text descriptions belong to '.$alternativeCommand.'.')
            ->onViolation('STOP. Report: "Invalid task ID. Use '.$alternativeCommand.' for text-based tasks or provide valid task ID."');
    }

    /**
     * Define auto-approval-flag rule.
     * Used by: TaskAsyncInclude, TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     */
    protected function defineAutoApprovalFlagRule(): void
    {
        $this->rule('auto-approval-flag')->critical()
            ->text('If $HAS_AUTO_APPROVE is true, auto-approve all approval gates. Skip approval checkpoints and proceed directly.')
            ->why('Flag -y enables automated execution without user interaction.')
            ->onViolation('Check $HAS_AUTO_APPROVE before showing approval checkpoint.');
    }

    /**
     * Define mandatory-user-approval rule (create/decompose style).
     * Used by: TaskCreateInclude, TaskDecomposeInclude
     */
    protected function defineMandatoryUserApprovalRule(): void
    {
        $this->rule('mandatory-user-approval')->critical()
            ->text('EVERY operation MUST have explicit user approval BEFORE execution. Present plan → WAIT for approval → Execute. NO auto-execution. EXCEPTION: If $HAS_AUTO_APPROVE is true, auto-approve.')
            ->why('User maintains control. No surprises. Flag -y enables automated execution.')
            ->onViolation('STOP. Wait for explicit user approval (unless $HAS_AUTO_APPROVE is true).');
    }


    // =========================================================================
    // COMMENT CONTEXT ANALYSIS (READ ACCUMULATED CONTEXT)
    // =========================================================================

    /**
     * Define comment context analysis rules and extraction guideline.
     * Ensures task.comment is parsed for accumulated inter-session context before execution.
     * Comments contain: memory IDs, file paths, previous results, failures, blockers, decisions.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskValidateSyncInclude, TaskTestValidateInclude, TaskDecomposeInclude, TaskBrainstormInclude.
     */
    protected function defineCommentContextRules(): void
    {
        $this->rule('comment-context-mandatory')->critical()
            ->text('AFTER loading task: parse task.comment for accumulated context. Extract: memory IDs (#NNN), file paths, previous execution results, failure reasons, blockers, decisions made. Store as $COMMENT_CONTEXT. Pass to ALL agents alongside task.content.')
            ->why('Comments accumulate critical inter-session context: what was tried, what failed, what files were touched, what decisions were made. Ignoring comments = blind re-execution without history.')
            ->onViolation('Parse task.comment IMMEDIATELY after task_get. Extract actionable context. Include in agent prompts and planning.');

        $this->guideline('comment-context-extraction')
            ->goal('Extract actionable context from task.comment before any execution or delegation')
            ->example()
            ->phase('Parse $TASK.comment (may be multi-line with \\n\\n separators):')
            ->phase('  1. MEMORY IDs: extract #NNN or memory #NNN patterns → previous knowledge links')
            ->phase('  2. FILE PATHS: extract file paths (src/*, tests/*, app/*, etc.) → files already touched/identified')
            ->phase('  3. EXECUTION HISTORY: entries with "completed", "passed", "started", "Done" → what was already done')
            ->phase('  4. FAILURES: entries with "failed", "error", "stopped", "rolled back" → what went wrong and why')
            ->phase('  5. BLOCKERS: entries with "BLOCKED", "waiting for", "needs" → current impediments')
            ->phase('  6. DECISIONS: entries with "chose", "decided", "approach", "using" → decisions already locked in')
            ->phase('  7. MODE FLAGS: "TDD MODE", "light validation", special execution modes')
            ->phase(Store::as('COMMENT_CONTEXT', '{memory_ids: [], file_paths: [], execution_history: [], failures: [], blockers: [], decisions: [], mode_flags: []}'))
            ->phase('If comment is empty/null → $COMMENT_CONTEXT = {} (proceed without, no error)');
    }

    // =========================================================================
    // PHASE 0: VECTOR TASK LOADING
    // =========================================================================

    /**
     * Define phase0 task loading guideline.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $activationHeader Header text (e.g., '=== TASK:ASYNC ACTIVATED ===')
     * @param array $validStatuses Valid statuses for this command (e.g., ['pending', 'in_progress'] or ['completed', 'tested', 'validated'])
     * @param bool $includeSessionRecovery Whether to include session recovery logic
     */
    protected function definePhase0TaskLoadingGuideline(
        string $activationHeader,
        array $validStatuses = ['pending', 'in_progress'],
        bool $includeSessionRecovery = true
    ): void {
        $guideline = $this->guideline('phase0-task-loading')
            ->goal('Load vector task with full context using pre-captured $VECTOR_TASK_ID')
            ->example()
            ->phase(Operator::output([
                $activationHeader,
                '',
                '=== PHASE 0: VECTOR TASK LOADING ===',
                'Loading task #{$VECTOR_TASK_ID}...',
            ]))
            ->phase('Use pre-captured: $RAW_INPUT, $HAS_AUTO_APPROVE, $CLEAN_ARGS, $VECTOR_TASK_ID')
            ->phase('Validate $VECTOR_TASK_ID: must be numeric, extracted from "15", "#15", "task 15", "task:15", "task-15"')
            ->phase(VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK_ID}'))
            ->phase(Store::as('VECTOR_TASK',
                '{task object with title, content, status, parent_id, priority, tags, comment}'))
            ->phase(Operator::if('$VECTOR_TASK not found', [
                Operator::report('Vector task #$VECTOR_TASK_ID not found'),
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'ABORT command',
            ]));

        // Add session recovery logic if enabled
        if ($includeSessionRecovery) {
            $guideline->phase(Operator::if('$VECTOR_TASK.status === "in_progress"', [
                'Check status_history for session crash indicator',
                Store::as('LAST_HISTORY_ENTRY', '{last element of $VECTOR_TASK.status_history array}'),
                Operator::if('$LAST_HISTORY_ENTRY.to === null', [
                    Operator::output([
                        '⚠️ SESSION RECOVERY MODE',
                        'Task #{$VECTOR_TASK_ID} was in_progress but session crashed (status_history.to = null)',
                        'Resuming execution without status change.',
                        'WARNING: Previous execution stage unknown. Will verify what was completed via vector memory.',
                        'NOTE: Memory findings from crashed session should be verified against codebase.',
                    ]),
                    Store::as('IS_SESSION_RECOVERY', 'true'),
                ]),
                Operator::if('$LAST_HISTORY_ENTRY.to !== null', [
                    Operator::output([
                        '=== EXECUTION BLOCKED ===',
                        'Task #{$VECTOR_TASK_ID} is currently in_progress by another session.',
                        'Wait for completion or manually reset status if session crashed without history update.',
                    ]),
                    'ABORT execution',
                ]),
            ]));
        }

        // Add status validation based on valid statuses
        if (in_array('completed', $validStatuses, true)) {
            // For validation commands that work on completed tasks
            $guideline->phase(Operator::if('$VECTOR_TASK.status NOT IN ["'.implode('", "', $validStatuses).'"]', [
                Operator::report('Vector task #$VECTOR_TASK_ID has status: {$VECTOR_TASK.status}'),
                'Only tasks with status ['.implode(', ', $validStatuses).'] can be processed',
                'ABORT command',
            ]));
        } else {
            // For execution commands (async/sync)
            $guideline->phase(Operator::if('$VECTOR_TASK.status === "completed"', [
                Operator::report('Vector task #$VECTOR_TASK_ID already completed'),
                'Ask user: "Re-execute this task? (yes/no)"',
                'WAIT for user decision',
            ]));
        }

        // Parent and subtasks loading
        $guideline
            ->phase(Operator::if('$VECTOR_TASK.parent_id !== null', [
                VectorTaskMcp::call('task_get', '{task_id: $VECTOR_TASK.parent_id}'),
                Store::as('PARENT_TASK', '{parent task for broader context}'),
            ]))
            ->phase(VectorTaskMcp::call('task_list', '{parent_id: $VECTOR_TASK_ID, limit: 20}'))
            ->phase(Store::as('SUBTASKS', '{child tasks if any}'))
            ->phase(Store::as('TASK_DESCRIPTION', '$VECTOR_TASK.title + $VECTOR_TASK.content'))
            ->phase(Store::as('COMMENT_CONTEXT', '{parsed from $VECTOR_TASK.comment: memory_ids: [#NNN], file_paths: [...], execution_history: [...], failures: [...], blockers: [...], decisions: [], mode_flags: []}'))
            ->phase(Operator::output([
                '',
                '=== PHASE 0: VECTOR TASK LOADED ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {$VECTOR_TASK.status} | Priority: {$VECTOR_TASK.priority}',
                'Parent: {$PARENT_TASK.title or "none"}',
                'Subtasks: {count or "none"}',
                'Comment context: memory_ids={$COMMENT_CONTEXT.memory_ids}, files={$COMMENT_CONTEXT.file_paths}, failures={$COMMENT_CONTEXT.failures}, decisions={$COMMENT_CONTEXT.decisions}',
            ]));
    }

    // =========================================================================
    // AUTO-APPROVAL CHECKPOINT
    // =========================================================================

    /**
     * Define auto-approval checkpoint guideline.
     * Used by: TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $checkpointLabel Label for the checkpoint (e.g., 'VALIDATION', 'TEST VALIDATION')
     * @param string $phaseHeader Phase header (e.g., '=== PHASE 1: APPROVAL ===')
     */
    protected function defineAutoApprovalCheckpointGuideline(string $checkpointLabel, string $phaseHeader = '=== PHASE 1: APPROVAL ==='): void
    {
        $this->guideline('phase1-approval')
            ->goal('Check auto-approval flag and handle approval checkpoint')
            ->example()
            ->phase(Operator::output([$phaseHeader]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === true', [
                Operator::output(['Auto-approval enabled (-y flag). Skipping approval checkpoint.']),
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "'.$checkpointLabel.' started (auto-approved)", append_comment: true}'),
            ]))
            ->phase(Operator::if('$HAS_AUTO_APPROVE === false', [
                Operator::output([
                    '',
                    'APPROVAL CHECKPOINT',
                    'Task: #{$VECTOR_TASK_ID} - {$VECTOR_TASK.title}',
                    'Action: '.$checkpointLabel,
                    '',
                    'Type "approved" or "yes" to proceed.',
                    'Type "no" to abort.',
                ]),
                'WAIT for user approval',
                Operator::verify('User approved'),
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "in_progress", comment: "'.$checkpointLabel.' started", append_comment: true}'),
            ]));
    }

    // =========================================================================
    // COMPLETION STATUS UPDATE
    // =========================================================================

    /**
     * Define completion status update guideline.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $successStatus Status to set on success (e.g., 'completed', 'validated', 'tested')
     * @param string $processName Name of the process for messages (e.g., 'Execution', 'Validation', 'Test validation')
     */
    protected function defineCompletionStatusUpdateGuideline(string $successStatus, string $processName): void
    {
        $this->guideline('completion-status-update')
            ->goal('Update vector task status based on execution outcome')
            ->example()
            ->phase(Store::as('COMPLETION_SUMMARY', '{completed_steps, files_modified, outcomes, learnings}'))
            ->phase(VectorMemoryMcp::call('store_memory',
                '{content: "Completed task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}\\n\\nApproach: {summary}\\n\\nSteps: {outcomes}\\n\\nLearnings: {insights}\\n\\nFiles: {list}", category: "' . self::CAT_CODE_SOLUTION . '", tags: ["' . self::MTAG_SOLUTION . '", "' . self::MTAG_REUSABLE . '"]}'))
            ->phase(Operator::if('status === SUCCESS', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "'.$successStatus.'", comment: "'.$processName.' completed successfully. Files: {list}. Memory: #{memory_id}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} '.$successStatus]),
            ]))
            ->phase(Operator::if('status === PARTIAL', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, comment: "Partial completion: {completed}/{total} steps. Remaining: {list}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} progress saved (partial)']),
            ]))
            ->phase(Operator::if('status === FAILED', [
                VectorTaskMcp::call('task_update',
                    '{task_id: $VECTOR_TASK_ID, status: "stopped", comment: "'.$processName.' failed: {reason}. Completed: {completed}/{total}", append_comment: true}'),
                Operator::output(['Vector task #{$VECTOR_TASK_ID} stopped (failed)']),
            ]))
            ->phase(Operator::output([
                '',
                '=== '.strtoupper($processName).' COMPLETE ===',
                'Task #{$VECTOR_TASK_ID}: {$VECTOR_TASK.title}',
                'Status: {SUCCESS/PARTIAL/FAILED}',
                'Steps: {completed}/{total} | Files: {count} | Learnings stored',
                '{step_outcomes}',
            ]));
    }

    // =========================================================================
    // TASK CONSOLIDATION (5-8h BATCHES)
    // =========================================================================

    /**
     * Define task consolidation guideline.
     * Used by: TaskValidateInclude, TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $taskPrefix Prefix for created tasks (e.g., 'FIX|', 'TEST-FIX|')
     */
    protected function defineTaskConsolidationGuideline(string $taskPrefix): void
    {
        $this->guideline('task-consolidation')
            ->goal('Consolidate similar fixes into 5-8 hour batches to reduce task overhead')
            ->example()
            ->phase('Group related fixes by: file proximity, similar issue type, shared context')
            ->phase('Target batch size: 5-8 hours estimated work')
            ->phase('Split if batch > 8 hours into smaller coherent groups')
            ->phase(Operator::if('multiple related fixes found', [
                'Create single consolidated task with all related fixes',
                'Title format: "'.$taskPrefix.' {count} fixes in {area/component}"',
                'Content: List all individual fixes with file references',
                'Set estimate: sum of individual estimates (max 8h per task)',
            ]))
            ->phase(Operator::if('batch > 8 hours', [
                'Split into multiple tasks by logical grouping',
                'Each task 5-8 hours',
                'Maintain coherent scope per task',
            ]));
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    /**
     * Define error handling guideline.
     * Used by: TaskAsyncInclude, TaskSyncInclude, TaskValidateInclude,
     *          TaskTestValidateInclude, TaskValidateSyncInclude
     *
     * @param string $processName Name of the process (e.g., 'Execution', 'Validation')
     * @param string $alternativeCommand Alternative command to suggest (e.g., '/do:async', '/do:validate')
     */
    protected function defineErrorHandlingGuideline(string $processName, string $alternativeCommand): void
    {
        $this->guideline('error-handling')
            ->text('Graceful error handling with recovery options')
            ->example()
            ->phase()->if('vector task not found', [
                'Report: "Vector task #{id} not found"',
                'Suggest: Check task ID with '.VectorTaskMcp::method('task_list'),
                'Abort command',
            ])
            ->phase()->if('vector task already completed', [
                'Report: "Vector task #{id} already has status: completed"',
                'Ask user: "Do you want to re-execute this task?"',
                'WAIT for user decision',
            ])
            ->phase()->if('invalid task ID format', [
                'Report: "Invalid task ID format. Expected: 15, #15, task 15, task:15"',
                'Suggest: "Use '.$alternativeCommand.' for text-based task descriptions"',
                'Abort command',
            ])
            ->phase()->if('user rejects plan', [
                'Accept modifications',
                'Rebuild plan',
                'Re-submit for approval',
            ])
            ->phase()->if($processName.' step fails', [
                'Log: "Step {N} failed: {error}"',
                'Update task comment with failure details',
                'Offer options:',
                '  1. Retry current step',
                '  2. Skip and continue',
                '  3. Abort remaining steps',
                'WAIT for user decision',
            ]);
    }

    // =========================================================================
    // STATUS/PRIORITY FILTERS (for list/status commands)
    // =========================================================================

    // =========================================================================
    // PARALLEL ISOLATION (SHARED ACROSS ALL TASK COMMANDS)
    // =========================================================================

    /**
     * Define parallel isolation rules.
     * Strict criteria for marking tasks as parallel: true.
     * Prevents race conditions, file conflicts, and lost changes between concurrent tasks.
     * Used by: ALL task commands that create or execute parallel tasks.
     */
    protected function defineParallelIsolationRules(): void
    {
        $this->rule('parallel-isolation-mandatory')->critical()
            ->text('Before setting parallel: true, ALL isolation conditions MUST be verified: 1) ZERO file overlap — tasks touch completely different files, 2) ZERO import chain — file A does NOT import/use/require anything from file B scope, 3) ZERO shared model/table — tasks do NOT modify same DB table/migration/model, 4) ZERO shared config — tasks do NOT modify same config key/.env variable, 5) ZERO output→input — task B does NOT need result/output of task A. ALL five MUST be TRUE.')
            ->why('Parallel tasks with shared files or dependencies cause race conditions, lost changes, and merge conflicts. LLM agents cannot lock files.')
            ->onViolation('Set parallel: false. When in doubt, sequential is always safe.');

        $this->rule('parallel-file-manifest')->critical()
            ->text('Before marking ANY task parallel: true, EXPLICITLY list ALL files each task will read/write/create. Cross-reference lists. If ANY file appears in 2+ tasks → parallel: false for ALL overlapping tasks. No exceptions.')
            ->why('Implicit file overlap is the #1 cause of parallel task conflicts. Explicit manifest prevents it.')
            ->onViolation('Create file manifest per task. Cross-reference. Overlap found = parallel: false.');

        $this->rule('parallel-conservative-default')->high()
            ->text('Default is parallel: false. Only set parallel: true when ALL isolation conditions are PROVEN. Uncertain about independence = sequential. Cost of wrong parallel (lost work, conflicts) far exceeds cost of wrong sequential (slower execution).')
            ->why('False negative (missing parallelism) = slower. False positive (wrong parallelism) = data loss. Asymmetric risk demands conservative default.')
            ->onViolation('Revert to parallel: false.');

        $this->rule('parallel-transitive-deps')->high()
            ->text('Check transitive dependencies: if task A modifies file X, and file X is imported by file Y, and task B modifies file Y — tasks A and B are NOT independent. Follow import/use/require chains one level deep minimum.')
            ->why('Indirect dependencies through shared modules cause subtle race conditions and inconsistent state.')
            ->onViolation('Trace import chain one level. Any indirect overlap = parallel: false.');
    }

    /**
     * Define parallel isolation checklist guideline.
     * Step-by-step verification procedure for task independence.
     * Used by: TaskDecomposeInclude, TaskCreateInclude, TaskBrainstormInclude (task creation workflows).
     */
    protected function defineParallelIsolationChecklistGuideline(): void
    {
        $this->guideline('parallel-isolation-checklist')
            ->goal('Systematic verification of task independence before setting parallel: true')
            ->example()
            ->phase('For EACH pair of tasks being considered for parallel execution:')
            ->phase('  1. FILE MANIFEST: List ALL files each task will read/write/create')
            ->phase('  2. FILE OVERLAP: Cross-reference manifests → shared file = parallel: false for BOTH')
            ->phase('  3. IMPORT CHAIN: Check if any file in task A imports/uses files from task B scope (and vice versa)')
            ->phase('  4. SHARED MODEL: Check if tasks modify same DB table, model, or migration')
            ->phase('  5. SHARED CONFIG: Check if tasks modify same config key, .env variable, or shared state')
            ->phase('  6. OUTPUT→INPUT: Check if task B needs any result/artifact/output from task A')
            ->phase('  7. TRANSITIVE: Follow imports one level deep — indirect overlap = NOT independent')
            ->phase('  8. GLOBAL BLACKLIST: If ANY task modifies globally shared files (dependency manifests/locks, .env*, config/**, routes/**, migration directories, CI/CD configs, test/lint/build configs) → that task MUST be parallel: false. Globally shared files are NEVER safe for parallel modification.')
            ->phase('  RESULT: ALL checks pass → parallel: true | ANY check fails → parallel: false')
            ->phase('  DEFAULT: When analysis is uncertain or incomplete → parallel: false (safe default)');
    }

    // =========================================================================
    // CODEBASE PATTERN REUSE (CONSISTENCY & DRY)
    // =========================================================================

    /**
     * Define codebase pattern reuse rule.
     * Ensures agents search for similar existing implementations before writing new code.
     * Prevents reinventing the wheel and maintains codebase consistency.
     * Used by: TaskSyncInclude, TaskAsyncInclude, TaskDecomposeInclude, TaskCreateInclude, TaskBrainstormInclude.
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
     * Used by: TaskSyncInclude, TaskAsyncInclude (as agent instruction).
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
     * Used by: TaskSyncInclude, TaskAsyncInclude, TaskDecomposeInclude, TaskCreateInclude.
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
     * Used by: TaskSyncInclude (inline), TaskAsyncInclude (via agent instructions).
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
     * AI code has 75% more logic bugs - this rule counteracts that.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions).
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
     * AI code has 8x more performance issues, especially I/O.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions), TaskDecomposeInclude.
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
     * Different from no-hallucination (tool results) - this is about CODE content.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions).
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
     * AI refactoring often leaves unused imports and orphaned code.
     * Used by: TaskSyncInclude, TaskAsyncInclude (via agent instructions).
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
     * Prevents round-trip: implement → validate → fix-task for tests → implement tests.
     * The executor who just wrote the code understands it best — better tests than a cold-read agent.
     * Used by: TaskSyncInclude, TaskAsyncInclude.
     */
    protected function defineTestCoverageDuringExecutionRule(): void
    {
        $this->rule('test-coverage-during-execution')->critical()
            ->text('After implementation: check if changed code has test coverage. If NO tests exist for changed files → WRITE tests. If tests exist but coverage insufficient → ADD missing tests. Target thresholds (MUST match validator expectations): >=80% coverage, critical paths 100%, meaningful assertions (not just "no exception"), edge cases (null, empty, boundary). Follow existing test patterns in the project (detect framework, mirror directory structure, reuse base test classes). NEVER skip — missing tests = guaranteed fix-task from validator = wasted round-trip.')
            ->why('Validator expects >=80% coverage with edge cases. Missing tests = validator creates fix-task = another execution cycle. The executor understands context best and writes better tests than a cold-read agent later.')
            ->onViolation('BEFORE marking task complete: verify test coverage for ALL changed files. No tests = write them NOW. Insufficient coverage = add tests NOW.');
    }

    // =========================================================================
    // TEST SCOPING (SCOPED VS FULL SUITE)
    // =========================================================================

    /**
     * Define test scoping rule.
     * Ensures test execution is scoped to task-related files for subtasks,
     * while root tasks run the full test suite.
     * Prevents wasting time running entire test suite for every small task.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineTestScopingRule(): void
    {
        $this->rule('test-scoping')->critical()
            ->text('Test execution MUST be scoped based on task hierarchy level. SUBTASK (has parent_id): run ONLY tests related to changed files — a) test files that directly test changed classes/modules, b) test files that import/use/depend on changed classes (reverse dependency in test directory). ROOT TASK (no parent_id): run the FULL test suite via quality gate command. NEVER run full test suite for subtasks — it wastes more time than the task itself.')
            ->why('Full test suite for a 1-hour subtask can take longer than the task execution itself. Scoped tests catch 95%+ of regressions at 10% of the cost. Full suite runs at root aggregation level and manually before push.')
            ->onViolation('Check task.parent_id. Has parent → scoped tests only. No parent → full suite allowed.');
    }

    // =========================================================================
    // STATUS/PRIORITY FILTERS
    // =========================================================================

    /**
     * Define status and priority icon mappings.
     * Used by: TaskListInclude, TaskStatusInclude
     */
    protected function defineStatusPriorityIconsGuideline(): void
    {
        $this->guideline('status-priority-icons')
            ->goal('Format task output with clear visual hierarchy using emojis and readable structure')
            ->text('Status icons: 📝 draft, ⏳ pending, 🔄 in_progress, ✅ completed, 🧪 tested, ✓ validated, ⏸️ stopped, ❌ canceled')
            ->text('Priority icons: 🔴 critical, 🟠 high, 🟡 medium, 🟢 low')
            ->text('Always prefix status/priority with corresponding emoji')
            ->text('Group tasks by status or parent, use indentation for hierarchy')
            ->text('Show key info inline: ID, title, priority, estimate')
            ->text('Use blank lines between groups for readability');
    }

    // =========================================================================
    // NO DESTRUCTIVE GIT (PROTECT PARALLEL AGENTS & MEMORY)
    // =========================================================================

    /**
     * Define no-destructive-git rules.
     * Prohibits git commands that modify working tree state.
     * memory/ contains SQLite databases (vector memory + tasks) tracked in git.
     * Any git checkout/stash/restore/reset/clean destroys uncommitted work
     * from parallel agents AND wipes vector memory and task databases.
     * Used by: ALL task execution, validation, and delegation commands.
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
    // PARALLEL EXECUTION AWARENESS (RUNTIME CONTEXT)
    // =========================================================================

    /**
     * Define parallel execution awareness rules.
     * When a task has parallel: true, the executing agent MUST understand
     * that sibling tasks may be running concurrently on the same codebase.
     * Agent must stay strictly within its task's file scope and never touch
     * files that siblings might be modifying or globally shared files
     * (dependency manifests, .env*, config, routes, migrations, CI/CD, test configs).
     * Complements defineParallelIsolationRules() which covers task CREATION.
     * This method covers task EXECUTION.
     * Used by: TaskSyncInclude, TaskAsyncInclude.
     */
    protected function defineParallelExecutionAwarenessRules(): void
    {
        $this->rule('parallel-execution-awareness')->critical()
            ->text('If $TASK.parallel === true: you are in PARALLEL CONTEXT. Other agents may be executing sibling tasks RIGHT NOW on the same codebase. IMMEDIATELY after loading task: fetch sibling tasks (same parent_id, parallel: true) to understand what they touch. Build $PARALLEL_SIBLINGS context. Stay STRICTLY within your task file scope.')
            ->why('parallel: true means this task was designed to run concurrently with siblings. Without awareness of sibling scopes, agent may accidentally modify shared files, causing conflicts and lost work across parallel sessions.')
            ->onViolation('Fetch siblings with same parent_id. Build parallel context. Restrict to own file scope.');

        $this->rule('parallel-strict-scope')->critical()
            ->text('In PARALLEL CONTEXT: modify ONLY files explicitly described in your task content or directly required by it. If you need to modify a file NOT in your task scope → DO NOT modify it. Record in task comment: "SCOPE EXTENSION NEEDED: {file} — reason: {why}". Let validation or next sequential task handle it.')
            ->why('Parallel sibling may be modifying that same file right now. Touching out-of-scope files = race condition, merge conflict, or overwritten work.')
            ->onViolation('ABORT out-of-scope edit. Add to task comment as scope extension request. Continue with in-scope work only.');

        $this->rule('parallel-shared-files-forbidden')->high()
            ->text('In PARALLEL CONTEXT: globally shared files are FORBIDDEN to edit regardless of sibling scopes. GLOBAL BLACKLIST (identify by project-specific patterns): 1) DEPENDENCY MANIFESTS & LOCKS — package definitions and lock files (composer.json, package.json, Gemfile, go.mod, Cargo.toml, requirements.txt and .lock counterparts), 2) ENVIRONMENT — .env* files, 3) GLOBAL CONFIG — config/**, settings/**, 4) ROUTING — routes/**, 5) SCHEMA/MIGRATIONS — migration directories, schema definitions (database/migrations/**, db/migrate/**), 6) INFRASTRUCTURE/CI — CI/CD pipelines, container configs, build scripts (.github/**, .gitlab-ci.yml, Dockerfile*, docker-compose*, docker/**, Makefile, Jenkinsfile), 7) TEST/LINT/BUILD CONFIG — root-level runner/linter/bundler configs (phpunit.xml*, jest.config*, tsconfig.json, .eslintrc*, vite.config*). Also blacklisted: any service/utility referenced by 2+ sibling tasks. If task REQUIRES blacklisted file → record in comment: "BLOCKED: needs {file} (globally shared). Defer to sequential phase." Complete non-blacklisted work first.')
            ->why('Globally shared files are statically known conflict sources — two agents editing same routes/config/migration simultaneously = one overwrites the other. Unlike sibling-scope files detected at runtime, blacklisted categories are ALWAYS shared regardless of task content. Explicit blacklist removes agent guesswork.')
            ->onViolation('ABORT edit of blacklisted file. Record in task comment: "BLOCKED: needs {file} (globally shared)". Complete remaining in-scope work. Blacklisted file edits handled sequentially after parallel phase.');

        $this->rule('parallel-scope-in-comment')->critical()
            ->text('In PARALLEL CONTEXT: after planning (when actual files known), STORE own scope in task comment via task_update: "PARALLEL SCOPE: [file1.php, file2.php, ...]" with append_comment: true. Siblings read your scope from task comment (already fetched via task_list — ZERO extra MCP calls). Do NOT store scopes in vector memory — scopes are ephemeral structured data, not semantic knowledge.')
            ->why('Task comments are free (come with task_list). Scopes are temporary file lists, not insights. Vector memory is for learnings/patterns, not ephemeral execution state. Comments self-clean when task is deleted.')
            ->onViolation('After planning: task_update with scope in comment. Read sibling scopes from their comments via task_list.');

        $this->rule('parallel-status-interpretation')->high()
            ->text('parallel: true does NOT mean siblings are running RIGHT NOW. It means they CAN run concurrently. Status interpretation: pending = not started, zero threat, ignore for conflict detection. completed = already done, files stable and committed, no active conflict. in_progress = potentially active, the ONLY status that matters for conflict detection. in_progress WITHOUT scope in memory = sibling still planning or just started, NOT a red flag, proceed normally. in_progress WITH scope in memory = REAL concurrent data, cross-reference for conflicts. Do NOT restrict yourself based on pending/completed siblings. Do NOT panic when in_progress sibling has no memory scope.')
            ->why('Without status interpretation, agents overreact: restrict themselves for pending tasks that haven\'t started, fear completed tasks that are done, panic when in_progress siblings lack memory scope. Causes unnecessary self-limitation and blocked work.')
            ->onViolation('Check sibling STATUS before reacting. Only in_progress + registered scope = actionable conflict data. Everything else = awareness only, not restriction.');
    }

    // =========================================================================
    // VALIDATOR PARALLEL COSMETIC DEFERRAL
    // =========================================================================

    /**
     * Define validator-specific parallel cosmetic deferral rule.
     * Validators making inline cosmetic fixes must check if the target file
     * is in an active sibling's scope before editing.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineValidatorParallelCosmeticRule(): void
    {
        $this->rule('validator-parallel-cosmetic-defer')->high()
            ->text('In PARALLEL CONTEXT: before making inline cosmetic fix, check if file is in ACTIVE sibling\'s scope (from $SIBLING_SCOPES). File in active sibling scope → DO NOT fix, record in task comment: "DEFERRED COSMETIC: {file}:{line} — {issue}. Reason: file in active sibling #{id} scope." File NOT in any active scope → safe to fix inline. This applies to ALL inline fixes: whitespace, formatting, typos, import sorting, comment cleanup.')
            ->why('Validator cosmetic fixes (Edit) on files being actively modified by a parallel executor = race condition. Even a whitespace fix overwrites the executor\'s in-memory file content, creating silent data loss or merge conflicts.')
            ->onViolation('Check $SIBLING_SCOPES before Edit. Active sibling owns file → defer cosmetic fix to task comment. Fix will be picked up by next validation pass after sibling completes.');
    }

    // =========================================================================
    // SCOPED GIT CHECKPOINT (MEMORY/ SACRED)
    // =========================================================================

    /**
     * Define scoped git checkpoint rule.
     * Git commits during validation MUST exclude memory/ and scope to task files in parallel context.
     * Used by: TaskValidateInclude, TaskValidateSyncInclude, TaskTestValidateInclude.
     */
    protected function defineScopedGitCheckpointRule(): void
    {
        $this->rule('scoped-git-checkpoint')->critical()
            ->text('Git checkpoint commits scope depends on context: 1) PARALLEL CONTEXT: "git add {task_file1} {task_file2}" — commit ONLY task-scope files. memory/ excluded implicitly (not in task files). Prevents staging other agents\' uncommitted work and SQLite binary conflicts. 2) NON-PARALLEL context: "git add -A" — full state checkpoint, INCLUDES memory/ for complete project state preservation. 3) If commit fails (pre-commit hook) → LOG and continue, work is still valid.')
            ->why('In parallel context, multiple agents write to memory/ SQLite and codebase concurrently. "git add -A" stages everything: other agents\' half-done work + binary SQLite mid-write = corrupted checkpoint. In non-parallel, "git add -A" is safe and DESIRED — memory/ commit preserves knowledge base alongside code for full revert capability.')
            ->onViolation('Parallel: "git add {specific_files}" (task scope only). Non-parallel: "git add -A" (full checkpoint with memory/).');
    }
}
