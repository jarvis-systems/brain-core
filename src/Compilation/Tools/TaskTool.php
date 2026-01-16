<?php

declare(strict_types=1);

namespace BrainCore\Compilation\Tools;

use BrainCore\Abstracts\ToolAbstract;
use Symfony\Component\VarExporter\VarExporter;

class TaskTool extends ToolAbstract
{
    public static function name(): string
    {
        return 'Task';
    }

    public static function agent(string $name, ...$args): string
    {
        foreach ($args as $index => $arg) {
            try{
                $args[$index] = VarExporter::export($arg);
            } catch (\Throwable) {
                $args[$index] = "'[unserializable]'";
            }
        }
        $agentName = puzzle('agent', $name);
        $argsStr = count($args) > 0 ? ' ' . implode(', ', $args) : '';
        // Format: [DELEGATE] @agent: 'prompt' - clearly NOT immediate tool call
        return "[DELEGATE] $agentName:$argsStr";
    }
}
