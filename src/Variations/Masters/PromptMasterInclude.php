<?php

declare(strict_types=1);

namespace BrainCore\Variations\Masters;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Runtime;
use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Provides guidelines and rules for creating and optimizing Brain.php, commands, and includes with quality prompts using PHP API.')]
class PromptMasterInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // COMMAND CREATION WORKFLOW
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('command-workflow')
            ->text('Command creation workflow from request to compiled output.')
            ->example()
            ->phase('analyze', 'Extract purpose, inputs, outputs, success criteria')
            ->phase('create', BashTool::call(BrainCLI::MAKE_COMMAND('{Name}')))
            ->phase('implement', ReadTool::call(Runtime::NODE_DIRECTORY('Commands/{Name}Command.php')) . ' → implement handle()')
            ->phase('compile', BashTool::call(BrainCLI::COMPILE))
            ->phase('verify', ReadTool::call(Runtime::COMMANDS_FOLDER('{name}.md')) . ' → validate output');

        // ═══════════════════════════════════════════════════════════════════
        // INCLUDE CREATION WORKFLOW
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('include-workflow')
            ->text('Include creation workflow for reusable prompt fragments.')
            ->example()
            ->phase('discover', BashTool::call(BrainCLI::LIST_INCLUDES) . ' → check existing')
            ->phase('decide', Operator::if('reused by 2+ components OR domain knowledge', 'create include', 'inline'))
            ->phase('create', BashTool::call(BrainCLI::MAKE_INCLUDE('{Name}')))
            ->phase('implement', ReadTool::call(Runtime::NODE_DIRECTORY('Includes/{Name}.php')) . ' → handle()')
            ->phase('attach', '#[Includes({Name}::class)] on target')
            ->phase('compile', BashTool::call(BrainCLI::COMPILE));

        $this->guideline('include-decision')
            ->text('Include vs inline decision criteria.')
            ->example('Include: reused 2+ times, domain-specific knowledge, complex structure')->key('include')
            ->example('Inline: one-time use, simple rules, component-specific')->key('inline')
            ->example('Attach: agents, commands, other includes (recursive to 255 depth)')->key('attach');

        // ═══════════════════════════════════════════════════════════════════
        // BRAIN.PHP EDITING WORKFLOW
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('brain-workflow')
            ->text('Brain.php editing workflow for project-specific configuration.')
            ->example()
            ->phase('read', ReadTool::call(Runtime::NODE_DIRECTORY('Brain.php')) . ' → analyze configuration')
            ->phase('discover', BashTool::call(BrainCLI::LIST_INCLUDES) . ' → list available')
            ->phase('analyze', 'Identify: missing includes, redundant rules, optimization opportunities')
            ->phase('edit', 'Edit tool with precise old_string/new_string')
            ->phase('compile', BashTool::call(BrainCLI::COMPILE))
            ->phase('verify', ReadTool::call(Runtime::BRAIN_FILE) . ' → validate');

        $this->guideline('brain-structure')
            ->text('Brain.php organization.')
            ->example('#[Includes(...)] at class level | handle() for project rules | comment separators | order: rules → guidelines → style → response → determinism')->key('structure')
            ->example('Includes: BrainCore (required) + Universal (CoreConstraints, QualityGates) + Domain (if applicable)')->key('includes')
            ->example('Optimization: target <3000 tokens, dedupe, merge similar, prune unused')->key('optimize');

        // ═══════════════════════════════════════════════════════════════════
        // PROMPT QUALITY
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('quality-criteria')
            ->text('Prompt quality requirements.')
            ->example('Clarity: single interpretation | Specificity: concrete actions | Brevity: min tokens | Actionable: maps to executable')->key('must-have')
            ->example('Avoid: "properly", "correctly", "as needed", "best practices", filler phrases, redundancy')->key('anti-patterns');

        // ═══════════════════════════════════════════════════════════════════
        // BUILDER API PATTERNS
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('builder-selection')
            ->text('Builder type selection.')
            ->example('guideline() → workflows, patterns, how-to | rule() → constraints, must/must-not | example()->phase() → sequential steps')->key('when')
            ->example('rule severity: critical (system break) > high (must fix) > medium (should fix) > low (suggestion)')->key('severity');

        // ═══════════════════════════════════════════════════════════════════
        // RESEARCH & OPTIMIZATION
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('research-triggers')
            ->text('When to search web for prompt patterns.')
            ->example(Operator::if('novel domain OR complex reasoning OR low confidence', WebSearchTool::describe('prompt engineering {domain} ' . Runtime::YEAR()), 'proceed with known patterns'))->key('trigger');

        $this->guideline('optimization-checklist')
            ->text('Pre-delivery optimization.')
            ->example()
            ->phase('dedup', 'Remove redundant instructions')
            ->phase('compress', 'Merge related guidelines')
            ->phase('clarify', 'Replace vague terms → specifics')
            ->phase('validate', 'Each instruction → single action');

        // ═══════════════════════════════════════════════════════════════════
        // AGENT DELEGATION
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('agent-embedding')
            ->text('Agent delegation in commands.')
            ->example(GlobTool::call(Runtime::AGENTS_FOLDER('*.md')) . ' → discover agents')->key('discover')
            ->example('TaskTool::agent("agent-id", "task") | AgentClass::call("task") | max 3 per command')->key('embed');

        // ═══════════════════════════════════════════════════════════════════
        // DIRECTIVE
        // ═══════════════════════════════════════════════════════════════════

        $this->guideline('directive')
            ->text('Core PromptMaster directive.')
            ->example('Use PHP API exclusively (BrainCore\\Compilation namespace)')->key('php-api')
            ->example('Runtime:: for paths, Store:: for variables, Operator:: for control flow')->key('helpers')
            ->example('Scan source before generating, compile always, never edit compiled output')->key('workflow')
            ->example('Store insights to vector memory after significant creations')->key('memory');

        // ═══════════════════════════════════════════════════════════════════
        // RULES
        // ═══════════════════════════════════════════════════════════════════

        $this->rule('no-placeholders')->critical()
            ->text('Generated prompts must contain zero placeholders or TODO markers.')
            ->why('Incomplete prompts cause runtime failures.')
            ->onViolation('Complete all placeholders before delivery.');

        $this->rule('token-efficiency')->high()
            ->text('Command prompts < 800 tokens, Brain.php < 3000 tokens after compilation.')
            ->why('Large prompts consume context and reduce effectiveness.')
            ->onViolation('Apply optimization-checklist to reduce size.');

        $this->rule('compile-verify')->critical()
            ->text('Always compile and verify after any source changes.')
            ->why('Syntax errors or invalid includes break entire Brain system.')
            ->onViolation(BashTool::call(BrainCLI::COMPILE) . ' → check errors → verify output.');

        $this->rule('memory-storage')->high()
            ->text('Store significant prompt patterns and learnings to vector memory.')
            ->why('Builds collective knowledge base for future prompt development.')
            ->onViolation(VectorMemoryMcp::callValidatedJson('store_memory', ['content' => '...', 'category' => 'code-solution', 'tags' => ['prompt', 'brain']]));
    }
}
