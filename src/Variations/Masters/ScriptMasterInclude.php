<?php

declare(strict_types=1);

namespace BrainCore\Variations\Masters;

use BrainCore\Archetypes\IncludeArchetype;
use BrainCore\Attributes\Purpose;
use BrainNode\Mcp\VectorMemoryMcp;

#[Purpose('Provides guidelines and rules for creating Brain scripts using Laravel Console v12.0 features and best practices.')]
class ScriptMasterInclude extends IncludeArchetype
{
    /**
     * Handle the architecture logic.
     *
     * @return void
     */
    protected function handle(): void
    {
        // === SIGNATURE SYNTAX (unique expertise) ===
        $this->guideline('signature-patterns')
            ->text('Command signature syntax for arguments and options.')
            ->example('{user}')->key('required-arg')
            ->example('{user?}')->key('optional-arg')
            ->example('{user=default}')->key('default-arg')
            ->example('{user*}')->key('array-arg')
            ->example('{--queue}')->key('boolean-option')
            ->example('{--queue=}')->key('value-option')
            ->example('{--queue=default}')->key('default-option')
            ->example('{--Q|queue=}')->key('shortcut-option')
            ->example('{--id=*}')->key('array-option')
            ->example('{user : Description}')->key('input-description');

        // === LARAVEL PROMPTS v12 (core expertise) ===
        $this->guideline('laravel-prompts')
            ->text('Modern interactive prompts (Laravel Prompts package, included in Laravel 12).')
            ->example('use function Laravel\\Prompts\\text;')->key('import')
            ->example('text(label, placeholder, default, required, validate, hint)')->key('text-input')
            ->example('password(label, placeholder, required, validate, hint)')->key('password-input')
            ->example('textarea(label, placeholder, required, validate, hint)')->key('multiline-input')
            ->example('confirm(label, default, yes, no, hint)')->key('yes-no')
            ->example('select(label, options, default, scroll, hint)')->key('single-choice')
            ->example('multiselect(label, options, default, required, scroll, hint)')->key('multi-choice')
            ->example('suggest(label, options, placeholder, default, required, validate, hint)')->key('autocomplete')
            ->example('search(label, options, placeholder, scroll, hint)')->key('search-filter')
            ->example('multisearch(label, options, placeholder, required, scroll, hint)')->key('search-multi')
            ->example('pause(message)')->key('pause-continue');

        $this->guideline('display-components')
            ->text('Output formatting and display helpers.')
            ->example('use function Laravel\\Prompts\\{note, info, warning, error, alert};')->key('messages')
            ->example('note(message), info(message), warning(message), error(message), alert(message)')->key('message-types')
            ->example('table(headers, rows)')->key('table-display')
            ->example('spin(callback, message)')->key('spinner')
            ->example('progress(label, steps, callback, hint)')->key('progress-bar');

        // === INPUT/OUTPUT ===
        $this->guideline('input-retrieval')
            ->text('Accessing command arguments and options.')
            ->example('$this->argument(\'user\')')->key('single-argument')
            ->example('$this->option(\'queue\')')->key('single-option')
            ->example('$this->arguments()')->key('all-arguments')
            ->example('$this->options()')->key('all-options');

        $this->guideline('legacy-io-methods')
            ->text('Traditional I/O methods (Laravel Prompts recommended for new scripts).')
            ->example('$this->info(), $this->error(), $this->warn(), $this->line()')->key('output-methods')
            ->example('$this->table(headers, data)')->key('table-legacy')
            ->example('$this->ask(), $this->secret(), $this->confirm()')->key('input-methods')
            ->example('$this->anticipate(), $this->choice()')->key('choice-methods')
            ->example('$this->withProgressBar(iterable, callback)')->key('progress-legacy');

        // === VALIDATION ===
        $this->guideline('validation')
            ->text('Input validation patterns for prompts and commands.')
            ->example('validate: fn($value) => match(true) { empty($value) => \'Required\', default => null }')->key('closure-validation')
            ->example('validate: [\'required\', \'email\']')->key('laravel-rules')
            ->example('required: true')->key('required-field')
            ->example('Validator::make($data, $rules)')->key('manual-validation');

        // === ADVANCED FEATURES ===
        $this->guideline('isolatable-commands')
            ->text('Ensure only one instance runs simultaneously.')
            ->example('use Illuminate\\Contracts\\Console\\Isolatable;')->key('interface')
            ->example('class ScriptName extends Command implements Isolatable')->key('implementation')
            ->example('Requires cache driver: memcached, Redis, DynamoDB, database, file, or array')->key('cache-requirement');

        $this->guideline('prompts-for-missing-input')
            ->text('Auto-prompt for required arguments when not provided.')
            ->example('use Illuminate\\Contracts\\Console\\PromptsForMissingInput;')->key('interface')
            ->example('class ScriptName extends Command implements PromptsForMissingInput')->key('implementation')
            ->example('protected function promptForMissingArgumentsUsing(): array')->key('customize-prompts')
            ->example('return [\'user\' => fn() => text(\'User ID\')];')->key('prompt-example');

        $this->guideline('signal-handling')
            ->text('Handle Unix signals for graceful shutdown.')
            ->example('$this->trap(SIGTERM, fn() => $this->shouldKeepRunning = false)')->key('single-signal')
            ->example('$this->trap([SIGTERM, SIGQUIT], function(int $signal) { ... })')->key('multiple-signals');

        $this->guideline('calling-other-commands')
            ->text('Execute other commands from within scripts.')
            ->example('$this->call(\'command:name\', [\'arg\' => $value])')->key('call-with-output')
            ->example('$this->callSilently(\'command:name\', [\'arg\' => $value])')->key('call-silent')
            ->example('Artisan::call(\'command:name\', [...])')->key('artisan-facade')
            ->example('Artisan::queue(\'command:name\', [...])->onQueue(\'commands\')')->key('queue-command');

        // === PERFORMANCE ===
        $this->guideline('performance-optimization')
            ->text('Performance patterns for Brain scripts.')
            ->example('User::lazy()->each(fn($user) => ...)')->key('lazy-collections')
            ->example('User::chunk(100, fn($chunk) => ...)')->key('chunking')
            ->example('Queue::push(ProcessJob::class)')->key('queue-heavy-tasks')
            ->example('Cache::remember(\'key\', 3600, fn() => ...)')->key('caching')
            ->example('DB::transaction(fn() => ...)')->key('transactions');

        // === TESTING ===
        $this->guideline('testing-scripts')
            ->text('Testing Brain scripts in PHPUnit tests.')
            ->example('$this->artisan(\'script:name\')->assertExitCode(0)')->key('exit-code-assertion')
            ->example('->expectsOutput(\'text\')')->key('output-assertion')
            ->example('->expectsQuestion(\'question\', \'answer\')')->key('question-assertion')
            ->example('->expectsConfirmation(\'question\', true)')->key('confirmation-assertion')
            ->example('->assertSuccessful(), ->assertFailed()')->key('status-assertions');

        // === ILLUMINATE PACKAGES ===
        $this->guideline('illuminate-package-integration')
            ->text('Leverage Illuminate packages in scripts.')
            ->example('Collection, Storage, Process, Validator')->key('support-classes')
            ->example('Bus, Log, Cache, DB')->key('facades');

        // === DIRECTIVE ===
        $this->guideline('directive')
            ->text('Core directive for ScriptMaster.')
            ->example('Use Laravel Prompts for modern interactive UX')->key('modern-prompts')
            ->example('Implement robust error handling and validation')->key('error-handling')
            ->example('Optimize for memory efficiency with lazy/chunk patterns')->key('performance')
            ->example('Test scripts thoroughly with PHPUnit assertions')->key('testing');

        // === RULES ===
        $this->rule('laravel-12-features')
            ->high()
            ->text('Exclusively use Laravel Console v12.0 features and patterns.')
            ->why('Scripts run on illuminate/console ^12.0 with Laravel Prompts package integrated.')
            ->onViolation('Update to Laravel 12 syntax and features.');

        $this->rule('memory-storage-mandatory')
            ->high()
            ->text('Store significant script patterns and learnings to vector memory after creation.')
            ->why('Builds collective knowledge base for future script development.')
            ->onViolation(VectorMemoryMcp::callValidatedJson('store_memory', ['content' => '{script insights}', 'category' => 'code-solution', 'tags' => ['script', 'brain']]));
    }
}
