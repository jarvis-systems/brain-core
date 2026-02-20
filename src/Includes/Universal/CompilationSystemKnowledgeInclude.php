<?php

declare(strict_types=1);

namespace BrainCore\Includes\Universal;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Variations\Traits\ModeResolverTrait;

#[Purpose('Brain compilation system knowledge: namespaces, PHP API, archetype structures. MANDATORY scanning of actual source files before code generation.')]
class CompilationSystemKnowledgeInclude extends IncludeArchetype
{
    use ModeResolverTrait;

    protected function handle(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // CRITICAL RULE: SCAN SOURCE FILES BEFORE ANY CODE GENERATION
        // ═══════════════════════════════════════════════════════════════════

        $this->rule('mandatory-source-scanning')->critical()
            ->text('BEFORE generating ANY Brain component code (Command, Agent, Skill, Include, MCP), you MUST scan actual PHP source files. Documentation may be outdated - SOURCE CODE is the ONLY truth.')
            ->why('PHP API evolves. Method signatures change. New helpers added. Only source code reflects current state.')
            ->onViolation('STOP. Execute scanning workflow FIRST. Never generate code from memory or documentation alone.');

        $this->guideline('scanning-workflow')
            ->text('MANDATORY scanning sequence before code generation.')
            ->example()
                ->phase('scan-1', GlobTool::call(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Compilation/**/*.php')))
                ->phase('scan-2', ReadTool::describe(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Compilation/Runtime.php'), 'Extract: constants, static methods with signatures'))
                ->phase('scan-3', ReadTool::describe(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Compilation/Operator.php'), 'Extract: ALL static methods (if, forEach, task, verify, validate, etc.)'))
                ->phase('scan-4', ReadTool::describe(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Compilation/Store.php'), 'Extract: as(), get() signatures'))
                ->phase('scan-5', ReadTool::describe(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Compilation/BrainCLI.php'), 'Extract: ALL constants and static methods'))
                ->phase('scan-6', GlobTool::call(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Compilation/Tools/*.php')))
                ->phase('scan-7', ReadTool::describe(Runtime::BRAIN_DIRECTORY('vendor/jarvis-brain/core/src/Abstracts/ToolAbstract.php'), 'Extract: call(), describe() base methods'))
                ->phase('scan-8', GlobTool::call(Runtime::NODE_DIRECTORY('Mcp/*.php')))
                ->phase('scan-9', 'Read MCP classes → Extract ::call(name, ...args) and ::id() patterns')
                ->phase('ready', 'NOW you can generate code using ACTUAL API from source');

        // ═══════════════════════════════════════════════════════════════════
        // REFERENCE SECTIONS (deep/exhaustive only)
        // ═══════════════════════════════════════════════════════════════════

        if ($this->isDeepCognitive()) {

        $this->guideline('namespaces-compilation')
            ->text('BrainCore\\Compilation namespace - pseudo-syntax generation helpers.')
            ->example('BrainCore\\Compilation\\Runtime - Path constants and methods')->key('runtime')
            ->example('BrainCore\\Compilation\\Operator - Control flow operators')->key('operator')
            ->example('BrainCore\\Compilation\\Store - Variable storage')->key('store')
            ->example('BrainCore\\Compilation\\BrainCLI - CLI command constants')->key('cli');

        $this->guideline('namespaces-tools')
            ->text('BrainCore\\Compilation\\Tools namespace - tool call generators.')
            ->example('BrainCore\\Compilation\\Tools\\BashTool')->key('bash')
            ->example('BrainCore\\Compilation\\Tools\\ReadTool')->key('read')
            ->example('BrainCore\\Compilation\\Tools\\EditTool')->key('edit')
            ->example('BrainCore\\Compilation\\Tools\\WriteTool')->key('write')
            ->example('BrainCore\\Compilation\\Tools\\GlobTool')->key('glob')
            ->example('BrainCore\\Compilation\\Tools\\GrepTool')->key('grep')
            ->example('BrainCore\\Compilation\\Tools\\TaskTool')->key('task')
            ->example('BrainCore\\Compilation\\Tools\\WebSearchTool')->key('websearch')
            ->example('BrainCore\\Compilation\\Tools\\WebFetchTool')->key('webfetch');

        $this->guideline('namespaces-archetypes')
            ->text('BrainCore\\Archetypes namespace - base classes for components.')
            ->example('BrainCore\\Archetypes\\AgentArchetype - Agents base')->key('agent')
            ->example('BrainCore\\Archetypes\\CommandArchetype - Commands base')->key('command')
            ->example('BrainCore\\Archetypes\\IncludeArchetype - Includes base')->key('include')
            ->example('BrainCore\\Archetypes\\SkillArchetype - Skills base')->key('skill')
            ->example('BrainCore\\Archetypes\\BrainArchetype - Brain base')->key('brain');

        $this->guideline('namespaces-mcp')
            ->text('MCP architecture namespace.')
            ->example('BrainCore\\Architectures\\McpArchitecture - MCP base class')->key('base')
            ->example('BrainCore\\Mcp\\StdioMcp - STDIO transport')->key('stdio')
            ->example('BrainCore\\Mcp\\HttpMcp - HTTP transport')->key('http')
            ->example('BrainCore\\Mcp\\SseMcp - SSE transport')->key('sse');

        $this->guideline('namespaces-attributes')
            ->text('BrainCore\\Attributes namespace - PHP attributes.')
            ->example('BrainCore\\Attributes\\Meta - Metadata attribute')->key('meta')
            ->example('BrainCore\\Attributes\\Purpose - Purpose description')->key('purpose')
            ->example('BrainCore\\Attributes\\Includes - Include reference')->key('includes');

        $this->guideline('namespaces-node')
            ->text('BrainNode namespace - user-defined components.')
            ->example('BrainNode\\Agents\\{Name}Master - Agent classes')->key('agents')
            ->example('BrainNode\\Commands\\{Name}Command - Command classes')->key('commands')
            ->example('BrainNode\\Skills\\{Name}Skill - Skill classes')->key('skills')
            ->example('BrainNode\\Mcp\\{Name}Mcp - MCP classes')->key('mcp')
            ->example('BrainNode\\Includes\\{Name} - Include classes')->key('includes');

        // ═══════════════════════════════════════════════════════════════════
        // COMPILE-TIME VARIABLES SYSTEM
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('var-system')
            ->text('Variable system for centralized configuration across archetypes. Resolution chain: ENV → Runtime → Meta → Method hook.')
            ->example('$this->var("name", $default) - Get variable with fallback chain')->key('get')
            ->example('$this->varIs("name", $value, $strict) - Compare variable to value')->key('compare')
            ->example('$this->varIsPositive("name") - Check if truthy (true, 1, "1", "true")')->key('positive')
            ->example('$this->varIsNegative("name") - Check if falsy')->key('negative');

        $this->guideline('var-resolution')
            ->text('Variable resolution order (first match wins).')
            ->example()
                ->phase('1-env', Runtime::BRAIN_DIRECTORY('.env') . ' - Environment file (UPPER_CASE names)')
                ->phase('2-runtime', 'Brain::setVariable() - Compiler runtime variables')
                ->phase('3-meta', '#[Meta("name", "value")] - Class attribute')
                ->phase('4-method', 'Local method hook - transforms/provides fallback value');

        $this->guideline('var-env')
            ->text('Environment variables in ' . Runtime::BRAIN_DIRECTORY('.env') . ' file.')
            ->example('Names auto-converted to UPPER_CASE: var("my_var") → reads MY_VAR')->key('case')
            ->example('Type casting: "true"/"false" → bool, "123" → int, "1.5" → float')->key('types')
            ->example('JSON arrays: "[1,2,3]" or "{\"a\":1}" → parsed arrays')->key('json')
            ->example(BrainCLI::COMPILE . ' --show-variables - View all runtime variables')->key('cli');

        $this->guideline('var-method-hook')
            ->text('Local method as variable hook/transformer. Method name = lowercase variable name.')
            ->example('protected function my_var(mixed $value): mixed { return $value ?? "fallback"; }')->key('signature')
            ->example('Hook receives: meta value or default → returns final value')->key('flow')
            ->example('Use case: conditional logic, computed values, complex fallbacks')->key('use');

        $this->guideline('var-usage')
            ->text('Common variable usage patterns.')
            ->example('Conditional: if ($this->varIsPositive("feature_x")) { ... }')->key('conditional')
            ->example('Value: $model = $this->var("default_model", "sonnet")')->key('value')
            ->example('Centralize: Define once in .env, use across all agents/commands')->key('centralize');

        // ═══════════════════════════════════════════════════════════════════
        // PHP API REFERENCE - VERIFIED FROM SOURCE
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('api-runtime')
            ->text('Runtime class: path constants and path-building methods.')
            ->example('Constants: PROJECT_DIRECTORY, BRAIN_DIRECTORY, NODE_DIRECTORY, BRAIN_FILE, BRAIN_FOLDER, AGENTS_FOLDER, COMMANDS_FOLDER, SKILLS_FOLDER, MCP_FILE, AGENT, DATE, TIME, YEAR, MONTH, DAY, TIMESTAMP, UNIQUE_ID')->key('constants')
            ->example('Methods: NODE_DIRECTORY(...$append), BRAIN_DIRECTORY(...$append), BRAIN_FOLDER(...$append), AGENTS_FOLDER(...$append), etc.')->key('methods')
            ->example('Usage: Runtime::NODE_DIRECTORY("Brain.php") → "{{ NODE_DIRECTORY }}Brain.php"')->key('usage');

        $this->guideline('api-operator')
            ->text('Operator class: control flow and workflow operators.')
            ->example('if(condition, then, else?) - Conditional block')->key('if')
            ->example('forEach(condition, body) - Loop block')->key('foreach')
            ->example('task(...body) - Task block')->key('task')
            ->example('validate(condition, fails?) - Validation block')->key('validate')
            ->example('verify(...args) - VERIFY-SUCCESS operator')->key('verify')
            ->example('check(...args) - CHECK operator')->key('check')
            ->example('goal(...args) - GOAL operator')->key('goal')
            ->example('scenario(...args) - SCENARIO operator')->key('scenario')
            ->example('report(...args) - REPORT operator')->key('report')
            ->example('skip(...args) - SKIP operator')->key('skip')
            ->example('note(...args) - NOTE operator')->key('note')
            ->example('context(...args) - CONTEXT operator')->key('context')
            ->example('output(...args) - OUTPUT operator')->key('output')
            ->example('input(...args) - INPUT operator')->key('input')
            ->example('do(...args) - Inline action sequence')->key('do')
            ->example('delegate(masterId) - DELEGATE-TO operator')->key('delegate');

        $this->guideline('api-store')
            ->text('Store class: variable storage operators.')
            ->example('as(name, ...values) - STORE-AS($name = values)')->key('as')
            ->example('get(name) - STORE-GET($name)')->key('get');

        $this->guideline('api-braincli')
            ->text('BrainCLI class: CLI command references.')
            ->example('Constants: COMPILE, HELP, DOCS, INIT, LIST, UPDATE, LIST_MASTERS, LIST_INCLUDES')->key('constants')
            ->example('Constants: MAKE_COMMAND, MAKE_INCLUDE, MAKE_MASTER, MAKE_MCP, MAKE_SKILL, MAKE_SCRIPT')->key('make-constants')
            ->example('Methods: MAKE_MASTER(...args), MAKE_COMMAND(...args), DOCS(...args), etc.')->key('methods')
            ->example('Usage: BrainCLI::COMPILE → "brain compile"')->key('usage-const')
            ->example('Usage: BrainCLI::MAKE_MASTER("Foo") → "brain make:master Foo"')->key('usage-method');

        $this->guideline('api-tools')
            ->text('Tool classes: all extend ToolAbstract with call() and describe() methods.')
            ->example('Base: call(...$parameters) → Tool(param1, param2, ...)')->key('call')
            ->example('Base: describe(command, ...steps) → Tool(command) → [steps] → END-Tool')->key('describe')
            ->example('TaskTool special: agent(name, ...args) → Task(' . $this->puzzle('agent', 'name') . ', args)')->key('task-agent')
            ->example('Usage: BashTool::call(BrainCLI::COMPILE) → "Bash(\'brain compile\')"')->key('bash-example')
            ->example('Usage: ReadTool::call(Runtime::NODE_DIRECTORY("Brain.php")) → "Read(\'{{ NODE_DIRECTORY }}Brain.php\')"')->key('read-example')
            ->example('Usage: TaskTool::agent("explore", "Find files") → "Task(' . $this->puzzle('agent', 'explore') . ' \'Find files\')"')->key('task-example');

        $this->guideline('api-mcp')
            ->text('MCP classes: call() for tool invocation, id() for reference.')
            ->example('call(name, ...args) → "mcp__{id}__{name}(args)"')->key('call')
            ->example('id(...args) → "mcp__{id}(args)"')->key('id')
            ->example('Usage: VectorMemoryMcp::callValidatedJson("search_memories", ["query" => "..."]) → "mcp__vector-memory__search_memories({...})"')->key('example');

        $this->guideline('api-agent')
            ->text('AgentArchetype: agent delegation methods.')
            ->example('call(...text) → Task(' . $this->puzzle('agent', 'id') . ', text) - Full task delegation')->key('call')
            ->example('delegate() → DELEGATE-TO(' . $this->puzzle('agent', 'id') . ') - Delegate operator')->key('delegate')
            ->example('id() → ' . $this->puzzle('agent', '{id}') . ' - Agent reference string')->key('id');

        $this->guideline('api-command')
            ->text('CommandArchetype: command reference methods.')
            ->example('id(...args) → "/command-id (args)" - Command reference string')->key('id');

        // ═══════════════════════════════════════════════════════════════════
        // ARCHETYPE STRUCTURES
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('structure-agent')
            ->text('Agent structure: full attributes, includes, AgentArchetype base.')
            ->example('#[Meta("id", "agent-id")]')->key('meta-id')
            ->example('#[Meta("model", "sonnet|opus|haiku")]')->key('meta-model')
            ->example('#[Meta("color", "blue|green|yellow|red")]')->key('meta-color')
            ->example('#[Meta("description", "Brief description for Task tool")]')->key('meta-desc')
            ->example('#[Purpose("Detailed purpose description")]')->key('purpose')
            ->example('#[Includes(BaseConstraints::class)] - REQUIRED includes')->key('includes')
            ->example('extends AgentArchetype')->key('extends')
            ->example('protected function handle(): void { ... }')->key('handle');

        $this->guideline('structure-command')
            ->text('Command structure: minimal attributes, NO includes, CommandArchetype base.')
            ->example('#[Meta("id", "command-id")]')->key('meta-id')
            ->example('#[Meta("description", "Brief description")]')->key('meta-desc')
            ->example('#[Purpose("Command purpose")]')->key('purpose')
            ->example('NO #[Includes()] - commands inherit Brain context')->key('no-includes')
            ->example('extends CommandArchetype')->key('extends')
            ->example('protected function handle(): void { ... }')->key('handle');

        $this->guideline('structure-include')
            ->text('Include structure: Purpose only, IncludeArchetype base.')
            ->example('#[Purpose("Include purpose")]')->key('purpose')
            ->example('extends IncludeArchetype')->key('extends')
            ->example('protected function handle(): void { ... }')->key('handle');

        $this->guideline('structure-mcp')
            ->text('MCP structure: Meta id, transport base class.')
            ->example('#[Meta("id", "mcp-id")]')->key('meta-id')
            ->example('extends StdioMcp|HttpMcp|SseMcp')->key('extends')
            ->example('protected static function defaultCommand(): string')->key('command')
            ->example('protected static function defaultArgs(): array')->key('args');

        } // end isDeepCognitive — namespaces, vars, API, structures

        // ═══════════════════════════════════════════════════════════════════
        // COMPILATION FLOW & DIRECTORIES
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('compilation-flow')
            ->text('Source → Compile → Output flow.')
            ->example(Runtime::NODE_DIRECTORY('*.php') . ' → ' . BrainCLI::COMPILE . ' → ' . Runtime::BRAIN_FOLDER())->key('flow');

        $this->guideline('directories')
            ->text('Source (editable) vs Compiled (readonly) directories.')
            ->example('SOURCE: ' . Runtime::NODE_DIRECTORY() . ' - Edit here (Brain.php, Agents/*.php, Commands/*.php, etc.)')->key('source')
            ->example('COMPILED: ' . Runtime::BRAIN_FOLDER() . ' - NEVER edit (auto-generated)')->key('compiled')
            ->example('Workflow: Edit source → ' . BashTool::call(BrainCLI::COMPILE) . ' → auto-generates compiled')->key('workflow');

        // ═══════════════════════════════════════════════════════════════════
        // CRITICAL RULES
        // ═══════════════════════════════════════════════════════════════════

        $this->rule('never-write-compiled')->critical()
            ->text('FORBIDDEN: Write/Edit to ' . Runtime::BRAIN_FOLDER() . ', ' . Runtime::AGENTS_FOLDER() . ', ' . Runtime::COMMANDS_FOLDER() . '. These are compilation artifacts.')
            ->why('Compiled files are auto-generated. Direct edits are overwritten on next compile.')
            ->onViolation('ABORT. Edit ONLY ' . Runtime::NODE_DIRECTORY() . '*.php sources, then run brain compile.');

        $this->rule('use-php-api')->critical()
            ->text('FORBIDDEN: String pseudo-syntax in source code. ALWAYS use PHP API from BrainCore\\Compilation namespace.')
            ->why('PHP API ensures type safety, IDE support, consistent compilation, and evolves with system.')
            ->onViolation('Replace ALL string syntax with PHP API calls. Scan handle() for violations.');

        $this->rule('use-runtime-variables')->critical()
            ->text('FORBIDDEN: Hardcoded paths. ALWAYS use Runtime:: constants/methods for paths.')
            ->why('Hardcoded paths break multi-target compilation and platform portability.')
            ->onViolation('Replace hardcoded paths with Runtime:: references.');

        $this->rule('commands-no-includes')->critical()
            ->text('Commands MUST NOT have #[Includes()] attributes. Commands inherit Brain context.')
            ->why('Commands execute in Brain context where includes are already loaded. Duplication bloats output.')
            ->onViolation('Remove ALL #[Includes()] from Command classes.');

        // ═══════════════════════════════════════════════════════════════════
        // BUILDER API & CLI (deep/exhaustive only)
        // ═══════════════════════════════════════════════════════════════════

        if ($this->isDeepCognitive()) {

        $this->guideline('builder-rules')
            ->text('Rule builder pattern.')
            ->example('$this->rule("id")->critical()|high()|medium()|low()')->key('severity')
            ->example('->text("Rule description")')->key('text')
            ->example('->why("Reason for rule")')->key('why')
            ->example('->onViolation("Action on violation")')->key('violation');

        $this->guideline('builder-guidelines')
            ->text('Guideline builder patterns.')
            ->example('$this->guideline("id")->text("Description")->example("Example")')->key('basic')
            ->example('->example("Value")->key("name") - Named key-value')->key('key-value')
            ->example('->example()->phase("step-1", "Description") - Phased workflow')->key('phases')
            ->example('->example()->do(["Action1", "Action2"]) - Action list')->key('do')
            ->example('->goal("Goal description") - Set goal')->key('goal')
            ->example('->scenario("Scenario description") - Set scenario')->key('scenario');

        $this->guideline('builder-style')
            ->text('Style, response, determinism builders (Brain/Agent only).')
            ->example('$this->style()->language("English")->tone("Analytical")->brevity("Medium")')->key('style')
            ->example('$this->response()->sections()->section("name", "brief", required)')->key('response')
            ->example('$this->determinism()->ordering("stable")->randomness("off")')->key('determinism');

        // ═══════════════════════════════════════════════════════════════════
        // CLI COMMANDS
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('cli-workflow')
            ->text('Brain CLI commands for component creation.')
            ->example(BrainCLI::MAKE_MASTER('Name') . ' → Edit ' . Runtime::NODE_DIRECTORY('Agents/NameMaster.php') . ' → ' . BrainCLI::COMPILE)->key('agent')
            ->example(BrainCLI::MAKE_COMMAND('Name') . ' → Edit ' . Runtime::NODE_DIRECTORY('Commands/NameCommand.php') . ' → ' . BrainCLI::COMPILE)->key('command')
            ->example(BrainCLI::MAKE_SKILL('Name') . ' → Edit ' . Runtime::NODE_DIRECTORY('Skills/NameSkill.php') . ' → ' . BrainCLI::COMPILE)->key('skill')
            ->example(BrainCLI::MAKE_INCLUDE('Name') . ' → Edit ' . Runtime::NODE_DIRECTORY('Includes/Name.php') . ' → ' . BrainCLI::COMPILE)->key('include')
            ->example(BrainCLI::MAKE_MCP('Name') . ' → Edit ' . Runtime::NODE_DIRECTORY('Mcp/NameMcp.php') . ' → ' . BrainCLI::COMPILE)->key('mcp')
            ->example(BrainCLI::LIST_MASTERS . ' - List available agents')->key('list-masters')
            ->example(BrainCLI::LIST_INCLUDES . ' - List available includes')->key('list-includes');

        $this->guideline('cli-debug')
            ->text('Debug mode for Brain CLI troubleshooting.')
            ->example('BRAIN_CLI_DEBUG=1 brain compile - Enable debug output with full stack traces')->key('debug')
            ->example('Use debug mode when compilation fails without clear error message')->key('when');

        } // end isDeepCognitive — builder, CLI

        // ═══════════════════════════════════════════════════════════════════
        // DIRECTIVE
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('directive')
            ->text('Core directives for Brain development.')
            ->example('SCAN-FIRST: Always scan source files before generating code')
            ->example('PHP-API: Use BrainCore\\Compilation classes, never string syntax')
            ->example('RUNTIME-PATHS: Use Runtime:: for all path references')
            ->example('SOURCE-ONLY: Edit only ' . Runtime::NODE_DIRECTORY() . ', never compiled output')
            ->example('COMPILE-ALWAYS: Run brain compile after any source changes');
    }
}
