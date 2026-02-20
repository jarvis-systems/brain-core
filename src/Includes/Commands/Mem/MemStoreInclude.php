<?php

declare(strict_types=1);

namespace BrainCore\Includes\Commands\Mem;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Memory storage specialist that analyzes content, detects duplicates, suggests category/tags, and stores after user approval.')]
class MemStoreInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // Iron Rules
        $this->rule('analyze-content')->critical()
            ->text('MUST analyze ' . Store::get('RAW_INPUT') . ' content before storing')
            ->why('Content analysis ensures proper categorization and prevents garbage storage')
            ->onViolation('Parse ' . Store::get('RAW_INPUT') . ', extract content, determine domain and type');

        $this->rule('check-duplicates')->high()
            ->text('MUST search for similar memories before storing')
            ->why('Prevents duplicate entries and wasted storage')
            ->onViolation('Execute ' . VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{content_summary}', 'limit' => 3]));

        $this->rule('mandatory-approval')->critical()
            ->text('MUST get user approval before storing memory')
            ->why('User must validate content, category, and tags before committing')
            ->onViolation('Present memory specification and wait for YES/APPROVE');

        // === COMMAND INPUT (IMMEDIATE CAPTURE) ===
        $this->guideline('input')
            ->text(Store::as('RAW_INPUT', '$ARGUMENTS'))
            ->text(Store::as('MEMORY_CONTENT', '{content to store extracted from $RAW_INPUT}'));

        // Role definition
        $this->guideline('role')
            ->text('Memory storage specialist that analyzes content, checks for duplicates, suggests appropriate category and tags, and stores memory after user approval.');

        // Workflow Step 1 - Parse Arguments
        $this->guideline('workflow-step1')
            ->text('STEP 1 - Parse ' . Store::get('RAW_INPUT'))
            ->example()
            ->phase('format-1', 'Direct content: /mem:store "This is the memory content"')
            ->phase('format-2', 'With params: /mem:store content="..." category=code-solution tags=php,laravel')
            ->phase('extract', 'Extract from ' . Store::get('RAW_INPUT') . ': content (required), category (optional), tags (optional)')
            ->phase('derive-content', Store::as('CONTENT', '{extract content from ' . Store::get('RAW_INPUT') . '}'))
            ->phase('derive-category', Store::as('CATEGORY', '{extract category from ' . Store::get('RAW_INPUT') . ' if provided}'))
            ->phase('derive-tags', Store::as('TAGS', '{extract tags from ' . Store::get('RAW_INPUT') . ' if provided}'))
            ->phase('output', Store::as('INPUT', '{' . Store::get('CONTENT') . ', ' . Store::get('CATEGORY') . '?, ' . Store::get('TAGS') . '?}'));

        // Workflow Step 2 - Check Duplicates
        $this->guideline('workflow-step2')
            ->text('STEP 2 - Search for Similar Memories')
            ->example()
            ->phase('summarize', 'Create short summary (20-30 words) of content for search')
            ->phase('search', VectorMemoryMcp::callValidatedJson('search_memories', ['query' => '{summary}', 'limit' => 5]))
            ->phase('analyze', Operator::if(
                'similar memories found with similarity > 0.8',
                Operator::do(
                    'WARN: Similar memory exists (ID: {id}, similarity: {score})',
                    'Show: "{content_preview}"',
                    'Ask: "Continue storing? (yes/no/update existing)"'
                ),
                Store::as('DUPLICATES', 'none')
            ));

        // Workflow Step 3 - Analyze and Suggest
        $this->guideline('workflow-step3')
            ->text('STEP 3 - Analyze Content and Suggest Category/Tags')
            ->example()
            ->phase('detect-category', Operator::if(
                Store::get('CATEGORY') . ' is empty',
                Operator::do(
                    'Analyze content domain and type',
                    'Suggest category based on content analysis'
                )
            ))
            ->phase('detect-tags', Operator::if(
                Store::get('TAGS') . ' is empty',
                Operator::do(
                    'Extract key topics, technologies, concepts',
                    'Suggest 3-5 relevant tags'
                )
            ))
            ->phase('output', Store::as('SUGGESTION', '{category, tags, reasoning}'));

        // Workflow Step 4 - Present for Approval
        $this->guideline('workflow-step4')
            ->text('STEP 4 - Present Memory for User Approval (MANDATORY)')
            ->example()
            ->phase('display-1', '--- Memory to Store ---')
            ->phase('display-2', 'Content: {content_preview} ({char_count} chars)')
            ->phase('display-3', 'Category: {category}')
            ->phase('display-4', 'Tags: {tags}')
            ->phase('display-5', Operator::if(
                Store::get('DUPLICATES') . ' !== none',
                'WARNING: Similar memories exist!'
            ))
            ->phase('prompt', 'Store this memory? (yes/no/modify)')
            ->phase('gate', Operator::validate(
                'User response is YES, APPROVE, Y, or CONFIRM',
                'Wait for explicit approval. Allow modifications if requested.'
            ));

        // Workflow Step 5 - Store Memory
        $this->guideline('workflow-step5')
            ->text('STEP 5 - Store Memory After Approval')
            ->example()
            ->phase('store', VectorMemoryMcp::callValidatedJson('store_memory', [
                'content' => Store::get('INPUT') . '.content',
                'category' => Store::get('SUGGESTION') . '.category',
                'tags' => Store::get('SUGGESTION') . '.tags',
            ]))
            ->phase('confirm', 'Display: "Memory stored successfully"');

        // Categories Reference
        $this->guideline('categories')
            ->text('Available memory categories')
            ->example('Implementations, patterns, working solutions')->key('code-solution')
            ->example('Resolved issues, root causes, fixes applied')->key('bug-fix')
            ->example('Design decisions, system structure, trade-offs')->key('architecture')
            ->example('Insights, discoveries, lessons learned')->key('learning')
            ->example('Workflows, tool patterns, configurations')->key('tool-usage')
            ->example('Debug approaches, troubleshooting steps')->key('debugging')
            ->example('Optimizations, benchmarks, metrics')->key('performance')
            ->example('Security patterns, vulnerabilities, fixes')->key('security')
            ->example('Anything that does not fit other categories')->key('other');

        // Tag Guidelines
        $this->guideline('tag-guidelines')
            ->text('Tag naming conventions')
            ->example('php, laravel, javascript, python, go')->key('language')
            ->example('api, database, auth, cache, queue')->key('domain')
            ->example('react, vue, tailwind, livewire')->key('framework')
            ->example('docker, nginx, redis, mysql')->key('infrastructure')
            ->example('testing, ci-cd, deployment, monitoring')->key('process');
    }
}
