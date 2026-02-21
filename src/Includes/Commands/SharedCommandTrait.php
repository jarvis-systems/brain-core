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

    // --- Task Tags: Strict Level ---
    protected const TAG_STRICT_RELAXED = 'strict:relaxed';
    protected const TAG_STRICT_STANDARD = 'strict:standard';
    protected const TAG_STRICT_STRICT = 'strict:strict';
    protected const TAG_STRICT_PARANOID = 'strict:paranoid';

    // --- Task Tags: Cognitive Level ---
    protected const TAG_COGNITIVE_MINIMAL = 'cognitive:minimal';
    protected const TAG_COGNITIVE_STANDARD = 'cognitive:standard';
    protected const TAG_COGNITIVE_DEEP = 'cognitive:deep';
    protected const TAG_COGNITIVE_EXHAUSTIVE = 'cognitive:exhaustive';

    // --- Task Tags: Batch ---
    protected const TAG_BATCH_TRIVIAL = 'batch:trivial';

    // --- Tag Alias Map (synonym → canonical, for NOT-format compilation) ---
    /** @var array<string, string> Known synonyms mapped to canonical tags */
    private const TAG_ALIASES = [
        // Domain aliases
        'authentication' => self::TAG_AUTH, 'authorization' => self::TAG_AUTH,
        'login' => self::TAG_AUTH, 'authn' => self::TAG_AUTH, 'authz' => self::TAG_AUTH,
        'db' => self::TAG_DATABASE, 'mysql' => self::TAG_DATABASE,
        'postgres' => self::TAG_DATABASE, 'sqlite' => self::TAG_DATABASE,
        'rest' => self::TAG_API, 'graphql' => self::TAG_API, 'endpoint' => self::TAG_API,
        'docker' => self::TAG_INFRA, 'deploy' => self::TAG_INFRA, 'server' => self::TAG_INFRA,
        'github-actions' => self::TAG_CI_CD, 'pipeline' => self::TAG_CI_CD,
        'schema' => self::TAG_MIGRATION, 'migrate' => self::TAG_MIGRATION,
        // Type aliases
        'fix' => self::TAG_BUGFIX, 'bug' => self::TAG_BUGFIX,
        'feat' => self::TAG_FEATURE, 'enhancement' => self::TAG_FEATURE,
        'refactoring' => self::TAG_REFACTOR, 'cleanup' => self::TAG_REFACTOR,
        'documentation' => self::TAG_DOCS,
        'testing' => self::TAG_TEST, 'tests' => self::TAG_TEST,
        'maintenance' => self::TAG_CHORE,
    ];

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

    /** @var string[] Strict level tags */
    protected const TASK_TAGS_STRICT = [
        self::TAG_STRICT_RELAXED, self::TAG_STRICT_STANDARD,
        self::TAG_STRICT_STRICT, self::TAG_STRICT_PARANOID,
    ];

    /** @var string[] Cognitive level tags */
    protected const TASK_TAGS_COGNITIVE = [
        self::TAG_COGNITIVE_MINIMAL, self::TAG_COGNITIVE_STANDARD,
        self::TAG_COGNITIVE_DEEP, self::TAG_COGNITIVE_EXHAUSTIVE,
    ];

    /** @var string[] Batch tags */
    protected const TASK_TAGS_BATCH = [
        self::TAG_BATCH_TRIVIAL,
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
    // STRICT MODE & COGNITIVE LEVEL CONSTANTS
    // =========================================================================

    /** @var array<string, int> Strict mode levels (higher = more rules) */
    private const STRICT_LEVELS = ['relaxed' => 0, 'standard' => 1, 'strict' => 2, 'paranoid' => 3];

    /** @var array<string, int> Cognitive levels (higher = deeper analysis) */
    private const COGNITIVE_LEVELS = ['minimal' => 0, 'standard' => 1, 'deep' => 2, 'exhaustive' => 3];

    // =========================================================================
    // LEVEL RESOLUTION HELPERS (COMPILE-TIME)
    // =========================================================================

    protected function resolveStrictLevel(): string
    {
        $level = strtolower((string) $this->var('STRICT_MODE', 'standard'));

        return isset(self::STRICT_LEVELS[$level]) ? $level : 'standard';
    }

    protected function resolveCognitiveLevel(): string
    {
        $level = strtolower((string) $this->var('COGNITIVE_LEVEL', 'standard'));

        return isset(self::COGNITIVE_LEVELS[$level]) ? $level : 'standard';
    }

    protected function strictAtLeast(string $level): bool
    {
        $current = self::STRICT_LEVELS[$this->resolveStrictLevel()] ?? 1;
        $required = self::STRICT_LEVELS[$level] ?? 1;

        return $current >= $required;
    }

    protected function cognitiveAtLeast(string $level): bool
    {
        $current = self::COGNITIVE_LEVELS[$this->resolveCognitiveLevel()] ?? 1;
        $required = self::COGNITIVE_LEVELS[$level] ?? 1;

        return $current >= $required;
    }

    // =========================================================================
    // TAG TAXONOMY HELPERS
    // =========================================================================

    /**
     * Format tags with NOT-aliases inline.
     * Produces: "auth (NOT: authentication, login, authn), database (NOT: db, mysql)" format.
     *
     * @param  string[]  $tags  Canonical tag list
     * @return string Formatted string with alias warnings
     */
    private function formatTagsWithAliases(array $tags): string
    {
        $aliasesByCanonical = [];
        foreach (self::TAG_ALIASES as $alias => $canonical) {
            $aliasesByCanonical[$canonical][] = $alias;
        }

        $parts = [];
        foreach ($tags as $tag) {
            if (isset($aliasesByCanonical[$tag])) {
                $parts[] = $tag.' (NOT: '.implode(', ', $aliasesByCanonical[$tag]).')';
            } else {
                $parts[] = $tag;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Parse comma-separated custom tags from .env value.
     *
     * @param  string  $value  Comma-separated tag string
     * @return string[] Parsed and cleaned tag array
     */
    private function parseCustomTags(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', strtolower($value)))));
    }

    // =========================================================================
    // TAG TAXONOMY RULES (COMPILE-TIME ENFORCEMENT)
    // =========================================================================

    /**
     * Define ALL tag taxonomy rules and guidelines (backward-compatible wrapper).
     * Calls all granular taxonomy methods: task tags, memory tags, safety escalation, cognitive level.
     * Used by: Commands that both create tasks AND store memories (e.g., task:async, task:validate).
     */
    protected function defineTagTaxonomyRules(): void
    {
        $this->defineTaskTagTaxonomyRules();
        $this->defineMemoryTagTaxonomyRules();
        $this->defineSafetyEscalationRules();
        $this->defineCognitiveLevelGuidelines();
    }

    /**
     * Define task tag taxonomy rules and guidelines.
     * Ensures task tags use ONLY predefined or project-custom values.
     * Includes: task-tags-predefined-only rule, task-tag-selection guideline,
     * mandatory-level-tags rule, batch-trivial rule (via defineBatchTrivialRule).
     * Used by: Commands that CREATE tasks (task:create, task:decompose, init-task, init-agents).
     */
    protected function defineTaskTagTaxonomyRules(): void
    {
        $customTaskTags = $this->parseCustomTags((string) $this->var('CUSTOM_TASK_TAGS', ''));

        $taskTagsAll = implode(', ', array_merge(
            self::TASK_TAGS_WORKFLOW,
            self::TASK_TAGS_TYPE,
            self::TASK_TAGS_DOMAIN,
            self::TASK_TAGS_STRICT,
            self::TASK_TAGS_COGNITIVE,
            self::TASK_TAGS_BATCH,
            $customTaskTags,
        ));

        $this->rule('task-tags-predefined-only')->critical()
            ->text('Task tags MUST use ONLY predefined'.($customTaskTags ? ' or project-custom' : '').' values. FORBIDDEN: inventing new tags, synonyms, variations. Allowed: '.$taskTagsAll.'.')
            ->text(Operator::scenario('Project with 30 modules needs per-module filtering → use CUSTOM_TASK_TAGS in .env for project-specific tags, not 30 new constants in core.'))
            ->text(Operator::scenario('Task about "user login flow" → tag: auth (NOT: login, authentication, user-auth). MCP normalizes at storage, but use canonical form at reasoning time.'))
            ->why('Ad-hoc tags cause tag explosion ("user-auth", "authentication", "auth" = same concept, search finds none). Predefined'.($customTaskTags ? ' + project-custom' : '').' list = consistent search. MCP normalizes aliases at storage layer, but reasoning-time canonical usage prevents drift.')
            ->onViolation('Normalize via NOT-list (e.g. authentication→auth, db→database). No canonical match → skip tag, put context in task content. Silent fix, no memory storage.');

        // Tag selection with NOT-format aliases
        $tagSelection = $this->guideline('task-tag-selection')
            ->goal('Select tags per task. Combine dimensions for precision.')
            ->text('WORKFLOW (pipeline stage): '.implode(', ', self::TASK_TAGS_WORKFLOW))
            ->text('TYPE (work kind): '.$this->formatTagsWithAliases(self::TASK_TAGS_TYPE))
            ->text('DOMAIN (area): '.$this->formatTagsWithAliases(self::TASK_TAGS_DOMAIN))
            ->text('STRICT LEVEL: '.implode(', ', self::TASK_TAGS_STRICT))
            ->text('COGNITIVE LEVEL: '.implode(', ', self::TASK_TAGS_COGNITIVE))
            ->text('BATCH: '.implode(', ', self::TASK_TAGS_BATCH));

        if ($customTaskTags) {
            $tagSelection->text('PROJECT (custom): '.implode(', ', $customTaskTags));
        }

        $tagSelection->text('Formula: 1 TYPE + 1 DOMAIN + 0-2 WORKFLOW + 1 STRICT + 1 COGNITIVE'.($customTaskTags ? ' + 0-2 PROJECT' : '').'. Example: ["feature", "api", "strict:standard", "cognitive:standard"] or ["bugfix", "auth", "validation-fix", "strict:strict", "cognitive:deep"].');

        // --- Mandatory level tags (CONSTITUTIONAL — always compiled) ---
        $this->rule('mandatory-level-tags')->critical()
            ->text('EVERY task MUST have exactly ONE strict:* tag AND ONE cognitive:* tag. Allowed strict: '.implode(', ', self::TASK_TAGS_STRICT).'. Allowed cognitive: '.implode(', ', self::TASK_TAGS_COGNITIVE).'. Missing level tags = assign based on task scope analysis.')
            ->why('Level tags enable per-task compilation and cognitive load calibration. Without them, system defaults apply blindly regardless of task complexity.')
            ->onViolation('Analyze task scope and assign: strict:{level} + cognitive:{level}. Simple rename = strict:relaxed + cognitive:minimal. Production auth = strict:strict + cognitive:deep.');
    }

    /**
     * Define memory tag taxonomy rules and guidelines.
     * Ensures memory tags and categories use ONLY predefined or project-custom values.
     * Includes: memory-tags-predefined-only rule, memory-categories-predefined-only rule,
     * memory-tag-selection guideline.
     * Used by: Commands that STORE memories (init-vector, task:async, do:async, mem:store).
     */
    protected function defineMemoryTagTaxonomyRules(): void
    {
        $customMemoryTags = $this->parseCustomTags((string) $this->var('CUSTOM_MEMORY_TAGS', ''));

        $memoryTagsAll = implode(', ', array_merge(
            self::MEMORY_TAGS_CONTENT,
            self::MEMORY_TAGS_SCOPE,
            $customMemoryTags,
        ));

        $this->rule('memory-tags-predefined-only')->critical()
            ->text('Memory tags MUST use ONLY predefined'.($customMemoryTags ? ' or project-custom' : '').' values. Allowed: '.$memoryTagsAll.'.')
            ->why('Unknown tags = unsearchable memories. Predefined'.($customMemoryTags ? ' + project-custom' : '').' = discoverable. MCP normalizes at storage, but use canonical form at reasoning time.')
            ->onViolation('Normalize to closest canonical tag. No match → skip tag.');

        $this->rule('memory-categories-predefined-only')->critical()
            ->text('Memory category MUST be one of: '.implode(', ', self::MEMORY_CATEGORIES).'. FORBIDDEN: "other", "general", "misc", or unlisted.')
            ->why('"other" is garbage nobody searches. Every memory needs meaningful category.')
            ->onViolation('Choose most relevant from predefined list.');

        // Memory tag selection
        $memorySelection = $this->guideline('memory-tag-selection')
            ->goal('Select 1-3 tags per memory. Combine dimensions.')
            ->text('CONTENT (kind): '.implode(', ', self::MEMORY_TAGS_CONTENT))
            ->text('SCOPE (breadth): '.implode(', ', self::MEMORY_TAGS_SCOPE));

        if ($customMemoryTags) {
            $memorySelection->text('PROJECT (custom): '.implode(', ', $customMemoryTags));
        }

        $memorySelection->text('Formula: 1 CONTENT + 0-1 SCOPE'.($customMemoryTags ? ' + 0-1 PROJECT' : '').'. Example: ["solution", "reusable"] or ["failure", "module-specific"]. Max 3 tags.');
    }

    /**
     * Define safety escalation rules for task-level protection.
     * Ensures critical code areas get minimum protection levels regardless of task tags.
     * Includes: safety-escalation-non-overridable rule, safety-escalation-patterns guideline.
     * Used by: Commands that EXECUTE tasks with file-level changes.
     */
    protected function defineSafetyEscalationRules(): void
    {
        $this->rule('safety-escalation-non-overridable')->critical()
            ->text('After loading task, check file paths in task.content/comment. If files match safety patterns → effective level MUST be >= pattern minimum, regardless of task tags or .env default. Agent tags are suggestions UPWARD only — can raise above safety floor, never lower below it.')
            ->text(Operator::scenario('Task tagged strict:relaxed touches auth/guards/LoginController.php → escalate to strict:strict minimum regardless of tag.'))
            ->text(Operator::scenario('Simple rename across 12 files → cognitive escalates to standard (>10 files rule), strict stays as tagged.'))
            ->why('Safety patterns guarantee minimum protection for critical code areas. Agent cannot "cheat" by under-tagging a task touching auth/ or payments/.')
            ->onViolation('Raise effective level to safety floor. Log escalation in task comment.');

        $this->guideline('safety-escalation-patterns')
            ->goal('Automatic level escalation based on file patterns and context')
            ->text('File patterns → strict minimum: auth/, guards/, policies/, permissions/ → strict. payments/, billing/, stripe/, subscription/ → strict. .env, credentials, secrets, config/auth → paranoid. migrations/, schema → strict. composer.json, package.json, *.lock → standard. CI/, .github/, Dockerfile, docker-compose → strict. routes/, middleware/ → standard.')
            ->text('Context patterns → level minimum: priority=critical → strict+deep. tag hotfix or production → strict+standard. touches >10 files → standard+standard. tag breaking-change → strict+deep. Keywords security/encryption/auth/permission → strict. Keywords migration/schema/database/drop → strict.');
    }

    // =========================================================================
    // COGNITIVE LEVEL GUIDELINES (CONSTITUTIONAL)
    // =========================================================================

    /**
     * Define cognitive level guideline based on resolved COGNITIVE_LEVEL.
     * Generates level-appropriate cognitive instructions for analysis depth.
     * CONSTITUTIONAL — always compiled regardless of strict level.
     * Used by: defineTagTaxonomyRules() (auto-called by all includes).
     */
    protected function defineCognitiveLevelGuidelines(): void
    {
        $level = $this->resolveCognitiveLevel();

        $memoryProbes = ['minimal' => '1 focused', 'standard' => '2-3 targeted', 'deep' => '3-5 comprehensive', 'exhaustive' => '5+ cross-referenced'];
        $failureHistory = ['minimal' => 'skip', 'standard' => 'recent only', 'deep' => 'full scan', 'exhaustive' => 'full + pattern analysis'];
        $research = ['minimal' => 'skip unless blocked', 'standard' => 'on error/ambiguity', 'deep' => 'proactive for complex tasks', 'exhaustive' => 'always + cross-reference'];
        $agentScaling = ['minimal' => 'minimum (1-2)', 'standard' => 'auto (2-3)', 'deep' => 'auto (3-4)', 'exhaustive' => 'maximum (4+)'];
        $commentParsing = ['minimal' => 'IDs only', 'standard' => 'basic parse', 'deep' => 'full parse', 'exhaustive' => 'parse + validate'];

        $this->guideline('cognitive-level')
            ->goal('Cognitive level: '.$level.' — calibrate analysis depth accordingly')
            ->text('Memory probes per phase: '.($memoryProbes[$level] ?? $memoryProbes['standard']))
            ->text('Failure history: '.($failureHistory[$level] ?? $failureHistory['standard']))
            ->text('Research (context7/web): '.($research[$level] ?? $research['standard']))
            ->text('Agent scaling: '.($agentScaling[$level] ?? $agentScaling['standard']))
            ->text('Comment parsing: '.($commentParsing[$level] ?? $commentParsing['standard']));
    }

    // =========================================================================
    // BATCH TRIVIAL RULE (STANDARD+)
    // =========================================================================

    /**
     * Define batch trivial grouping rule.
     * When ALL items are identical, trivial, and independent → single task with checklist.
     * Gated at standard+ (skipped at relaxed).
     * Used by: TaskCreateInclude, TaskDecomposeInclude.
     */
    protected function defineBatchTrivialRule(): void
    {
        if (! $this->strictAtLeast('standard')) {
            return;
        }

        $this->rule('batch-trivial-grouping')->high()
            ->text('When ALL items are: identical operation (rename, format, move) + trivial (<5 min each, no logic change) + independent (no cross-file dependencies) → create 1 task with checklist, tags: ['.self::TAG_BATCH_TRIVIAL.', '.self::TAG_STRICT_RELAXED.', '.self::TAG_COGNITIVE_MINIMAL.']. Do NOT decompose into separate subtasks.')
            ->text(Operator::scenario('Rename 5 CSS classes across 5 files → single task with checklist (identical, trivial, independent).'))
            ->text(Operator::scenario('Update 5 service classes with different logic each → NOT batch (different logic = not identical operation). Decompose into separate tasks.'))
            ->why('Trivial batch operations gain nothing from parallelism. 5 identical tasks waste 5x planning overhead.')
            ->onViolation('Evaluate if items are truly independent and trivial. If yes → single task with checklist.');
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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

        $this->rule('docs-are-law')->critical()
            ->text('Documentation is the SINGLE SOURCE OF TRUTH. If docs exist for task - FOLLOW THEM EXACTLY. No deviations, no "alternatives", no "options" that docs don\'t mention.')
            ->text(Operator::scenario('Docs say "use Repository pattern". Existing code uses Service pattern. → Follow docs (Repository), not existing code.'))
            ->text(Operator::scenario('Docs describe feature but skip error handling details. → Follow docs for main flow, use conservative approach for undocumented edge cases.'))
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
            ->phase('Generate 3-5 keyword variations: split CamelCase, strip suffixes (Test, Controller, Service, Repository, Handler), extract domain words, try parent context keywords')
            ->phase('Search ORDER: most specific → most general. Minimum 3 attempts before concluding "no docs"')
            ->phase('WRONG: brain docs "UserAuthServiceTest" → not found → done')
            ->phase('RIGHT: brain docs "UserAuthServiceTest" → brain docs "UserAuth" → brain docs "Authentication" → FOUND!')
            ->phase('STILL not found after 3+ attempts? → brain docs --undocumented → check if class exists but lacks documentation');
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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

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
            ->text(Operator::scenario('Task says "add validation". Client-side, server-side, or both? → In -y mode: choose server-side (conservative, safer). In interactive: ask ONE question about this specific gap.'))
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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

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
        if (! $this->strictAtLeast('strict')) {
            return;
        }

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
        if (! $this->strictAtLeast('strict')) {
            return;
        }

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
        if (! $this->strictAtLeast('strict')) {
            return;
        }

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
        if (! $this->strictAtLeast('strict')) {
            return;
        }

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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

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
        if (! $this->strictAtLeast('standard')) {
            return;
        }

        $this->rule('docs-during-execution')->high()
            ->text('After implementation: NEW feature/module/API without .docs/ → CREATE doc. Changed behavior with existing docs → UPDATE. Bugfix/refactor/trivial → SKIP. Use brain docs to check existing. YAML format: brain docs --help -v.')
            ->why('Documentation is declared "law" but executors never create it. Executor understands the code best — creating docs during execution costs near zero.')
            ->onViolation('Before completing: run brain docs for feature keywords. New feature without docs → create .docs/{feature}.md.');

        $this->guideline('docs-during-execution')
            ->goal('Decide whether to create/update documentation after implementation')
            ->example()
            ->phase('1. Task adds NEW feature/module/API? → CHECK docs')
            ->phase('2. Task CHANGES BEHAVIOR? → CHECK docs')
            ->phase('3. Bugfix/refactor/trivial (no behavior change)? → SKIP')
            ->phase('CHECK: ' . BashTool::call(BrainCLI::DOCS('{feature keywords}')) . ' → docs found?')
            ->phase('  YES + behavior changed → READ doc, UPDATE relevant sections')
            ->phase('  NO + new feature → CREATE .docs/{feature-name}.md (YAML format: brain docs --help -v)')
            ->phase('  NO + minor change → SKIP')
            ->phase('POST-IMPLEMENTATION: ' . BashTool::call(BrainCLI::DOCS('--undocumented')) . ' → new undocumented classes? → flag in task comment');
    }
}
