<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Node package integrity tests.
 *
 * Validates node/ PHP classes via file-based analysis (no class instantiation required).
 * Uses token_get_all and regex to verify structural contracts without BrainNode autoload.
 */
class NodeIntegrityTest extends TestCase
{
    private static string $nodeDir;

    private static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        // core/tests/ -> core/ -> project root
        self::$projectRoot = dirname(__DIR__, 2);
        self::$nodeDir = self::$projectRoot . '/node';
    }

    /**
     * Collect all PHP files recursively from a directory.
     *
     * @return array<string>
     */
    private static function phpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Extract PHP attribute names from file content using token analysis.
     *
     * @return array<array{name: string, args: array<string>}>
     */
    private static function extractAttributes(string $content): array
    {
        $attributes = [];
        $tokens = token_get_all($content);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            // PHP 8 attribute: T_ATTRIBUTE = #[
            if ($tokens[$i][0] === T_ATTRIBUTE) {
                $attrContent = '#[';
                $depth = 1;
                $i++;

                while ($i < $count && $depth > 0) {
                    if (is_array($tokens[$i])) {
                        $attrContent .= $tokens[$i][1];
                    } else {
                        $attrContent .= $tokens[$i];
                        if ($tokens[$i] === '[') {
                            $depth++;
                        }
                        if ($tokens[$i] === ']') {
                            $depth--;
                        }
                    }
                    $i++;
                }

                // Extract attribute name and first argument
                if (preg_match("/#\[(\w+)\((['\"])(.*?)\\2/", $attrContent, $m)) {
                    $attributes[] = ['name' => $m[1], 'args' => [$m[3]]];
                } elseif (preg_match("/#\[(\w+)\(/", $attrContent, $m)) {
                    $attributes[] = ['name' => $m[1], 'args' => []];
                }
            }
        }

        return $attributes;
    }

    /**
     * Get Meta attribute values for a specific key from file content.
     *
     * @return array<string>
     */
    private static function getMetaValues(string $content, string $key): array
    {
        $values = [];
        // Match #[Meta('key', 'value')] or #[Meta('key', <<<'HEREDOC' ... HEREDOC)]
        if (preg_match_all("/#\[Meta\(['\"]" . preg_quote($key, '/') . "['\"],\s*['\"]?(.*?)['\"]?\)\]/s", $content, $matches)) {
            $values = $matches[1];
        }
        // Also match heredoc variant
        if (preg_match_all("/#\[Meta\(['\"]" . preg_quote($key, '/') . "['\"],\s*<<<['\"]?\w+['\"]?\n(.*?)\n\w+\n\)\]/s", $content, $matches)) {
            $values = array_merge($values, $matches[1]);
        }

        return $values;
    }

    /**
     * Check if file has a specific attribute.
     */
    private static function hasAttribute(string $content, string $name): bool
    {
        return (bool) preg_match("/#\[" . preg_quote($name, '/') . "[\s(]/", $content);
    }

    // ── Test 1: All node classes have strict_types ──────────────────────

    public function testAllNodeClassesHaveStrictTypes(): void
    {
        $files = self::phpFiles(self::$nodeDir);
        $this->assertNotEmpty($files, 'No PHP files found in node/');

        $missing = [];
        foreach ($files as $file) {
            $head = file_get_contents($file, false, null, 0, 500);
            if ($head === false || !str_contains($head, 'declare(strict_types=1)')) {
                $missing[] = str_replace(self::$projectRoot . '/', '', $file);
            }
        }

        $this->assertEmpty(
            $missing,
            "Missing declare(strict_types=1) in:\n" . implode("\n", $missing)
        );
    }

    // ── Test 2: All agents have required attributes ─────────────────────

    public function testAllAgentsHaveRequiredAttributes(): void
    {
        $agentDir = self::$nodeDir . '/Agents';
        $files = self::phpFiles($agentDir);
        $this->assertNotEmpty($files, 'No agent files found in node/Agents/');

        $failures = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            $required = ['Meta', 'Purpose', 'Includes'];
            foreach ($required as $attr) {
                if (!self::hasAttribute($content, $attr)) {
                    $failures[] = "$relative: missing #[$attr]";
                }
            }

            // Meta('id') is mandatory
            $ids = self::getMetaValues($content, 'id');
            if (empty($ids)) {
                $failures[] = "$relative: missing #[Meta('id', ...)]";
            }

            // Meta('description') is mandatory for agents
            $descriptions = self::getMetaValues($content, 'description');
            if (empty($descriptions)) {
                $failures[] = "$relative: missing #[Meta('description', ...)]";
            }

            // Meta('model') is mandatory for agents
            $models = self::getMetaValues($content, 'model');
            if (empty($models)) {
                $failures[] = "$relative: missing #[Meta('model', ...)]";
            }
        }

        $this->assertEmpty(
            $failures,
            "Agent attribute violations:\n" . implode("\n", $failures)
        );
    }

    // ── Test 3: All commands have required attributes ────────────────────

    public function testAllCommandsHaveRequiredAttributes(): void
    {
        $commandDir = self::$nodeDir . '/Commands';
        $files = self::phpFiles($commandDir);
        $this->assertNotEmpty($files, 'No command files found in node/Commands/');

        $failures = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            // Meta('id') is mandatory
            $ids = self::getMetaValues($content, 'id');
            if (empty($ids)) {
                $failures[] = "$relative: missing #[Meta('id', ...)]";
            }

            // Meta('description') is mandatory
            $descriptions = self::getMetaValues($content, 'description');
            if (empty($descriptions)) {
                $failures[] = "$relative: missing #[Meta('description', ...)]";
            }
        }

        $this->assertEmpty(
            $failures,
            "Command attribute violations:\n" . implode("\n", $failures)
        );
    }

    // ── Test 4: All MCP classes have Meta('id') ─────────────────────────

    public function testAllMcpClassesHaveMetaId(): void
    {
        $mcpDir = self::$nodeDir . '/Mcp';
        $files = self::phpFiles($mcpDir);
        $this->assertNotEmpty($files, 'No MCP files found in node/Mcp/');

        $missing = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            $ids = self::getMetaValues($content, 'id');
            if (empty($ids)) {
                $missing[] = $relative;
            }
        }

        $this->assertEmpty(
            $missing,
            "MCP classes without #[Meta('id')]:\n" . implode("\n", $missing)
        );
    }

    // ── Test 5: MCP defaultCommand returns non-empty ────────────────────

    public function testMcpDefaultCommandPattern(): void
    {
        $mcpDir = self::$nodeDir . '/Mcp';
        $files = self::phpFiles($mcpDir);

        $failures = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            // StdioMcp classes must have defaultCommand()
            if (str_contains($content, 'extends StdioMcp')) {
                if (!preg_match('/protected\s+static\s+function\s+defaultCommand\(\)\s*:\s*string/', $content)) {
                    $failures[] = "$relative: missing defaultCommand() method";
                } elseif (preg_match("/function defaultCommand\(\)\s*:\s*string\s*\{\s*return\s*['\"]([^'\"]*)['\"];/", $content, $m)) {
                    if (empty(trim($m[1]))) {
                        $failures[] = "$relative: defaultCommand() returns empty string";
                    }
                }
            }

            // HttpMcp classes must have defaultUrl()
            if (str_contains($content, 'extends HttpMcp')) {
                if (!preg_match('/protected\s+static\s+function\s+defaultUrl\(\)\s*:\s*string/', $content)) {
                    $failures[] = "$relative: missing defaultUrl() method";
                }
            }
        }

        $this->assertEmpty(
            $failures,
            "MCP contract violations:\n" . implode("\n", $failures)
        );
    }

    // ── Test 6: MCP defaultArgs returns array ───────────────────────────

    public function testMcpDefaultArgsPattern(): void
    {
        $mcpDir = self::$nodeDir . '/Mcp';
        $files = self::phpFiles($mcpDir);

        $failures = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            // StdioMcp classes must have defaultArgs() returning array
            if (str_contains($content, 'extends StdioMcp')) {
                if (!preg_match('/protected\s+static\s+function\s+defaultArgs\(\)\s*:\s*array/', $content)) {
                    $failures[] = "$relative: missing defaultArgs(): array method";
                }
            }
        }

        $this->assertEmpty(
            $failures,
            "MCP defaultArgs violations:\n" . implode("\n", $failures)
        );
    }

    // ── Test 7: No secrets in node source files ─────────────────────────

    public function testNoSecretsInNodeSourceFiles(): void
    {
        $files = self::phpFiles(self::$nodeDir);

        $secretPatterns = [
            'github_pat_[A-Za-z0-9_]{10,}',
            'ctx7sk-[a-f0-9-]{8,}',
            'gsk_[A-Za-z0-9]{10,}',
            'sk-or-v1-[A-Za-z0-9]{10,}',
        ];
        $combinedPattern = '/' . implode('|', $secretPatterns) . '/';

        $leaked = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            if (preg_match($combinedPattern, $content, $m)) {
                $leaked[] = "$relative: contains secret pattern '{$m[0]}'";
            }
        }

        $this->assertEmpty(
            $leaked,
            "Secrets found in node source files:\n" . implode("\n", $leaked)
        );
    }

    // ── Test 8: No test/stub MCP files in production ────────────────────

    public function testNoTestStubMcpFiles(): void
    {
        $mcpDir = self::$nodeDir . '/Mcp';
        $files = self::phpFiles($mcpDir);

        $stubs = [];
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            if (preg_match('/^Test\d*Mcp$/', $basename)) {
                $stubs[] = str_replace(self::$projectRoot . '/', '', $file);
            }
        }

        $this->assertEmpty(
            $stubs,
            "Test/stub MCP files found (should be removed):\n" . implode("\n", $stubs)
        );
    }

    // ── Test 9: Commands must not include Brain/Universal includes ──────

    public function testCommandsDoNotIncludeBrainOrUniversalIncludes(): void
    {
        $commandDir = self::$nodeDir . '/Commands';
        $files = self::phpFiles($commandDir);
        $this->assertNotEmpty($files, 'No command files found in node/Commands/');

        $forbiddenNamespaces = [
            'BrainCore\\Includes\\Brain\\',
            'BrainCore\\Includes\\Universal\\',
            'BrainCore\\Variations\\Brain\\',
            'BrainCore\\Variations\\Universal\\',
        ];

        $violations = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            foreach ($forbiddenNamespaces as $ns) {
                if (str_contains($content, $ns)) {
                    $violations[] = "$relative: imports from forbidden namespace $ns";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Commands must not include Brain/Universal includes (already in Brain context):\n" . implode("\n", $violations)
        );
    }

    // ── Test 10: Agent IDs are unique ─────────────────────────────────────

    public function testAgentIdsAreUnique(): void
    {
        $agentDir = self::$nodeDir . '/Agents';
        $files = self::phpFiles($agentDir);
        $this->assertNotEmpty($files, 'No agent files found in node/Agents/');

        $ids = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            $fileIds = self::getMetaValues($content, 'id');
            foreach ($fileIds as $id) {
                $ids[$id][] = $relative;
            }
        }

        $duplicates = array_filter($ids, fn (array $files): bool => count($files) > 1);
        $messages = [];
        foreach ($duplicates as $id => $dupeFiles) {
            $messages[] = "$id: " . implode(', ', $dupeFiles);
        }

        $this->assertEmpty(
            $duplicates,
            "Duplicate agent IDs found:\n" . implode("\n", $messages)
        );
    }

    // ── Test 11: MCP IDs are unique ───────────────────────────────────────

    public function testMcpIdsAreUnique(): void
    {
        $mcpDir = self::$nodeDir . '/Mcp';
        $files = self::phpFiles($mcpDir);
        $this->assertNotEmpty($files, 'No MCP files found in node/Mcp/');

        $ids = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$projectRoot . '/', '', $file);

            $fileIds = self::getMetaValues($content, 'id');
            foreach ($fileIds as $id) {
                $ids[$id][] = $relative;
            }
        }

        $duplicates = array_filter($ids, fn (array $files): bool => count($files) > 1);
        $messages = [];
        foreach ($duplicates as $id => $dupeFiles) {
            $messages[] = "$id: " . implode(', ', $dupeFiles);
        }

        $this->assertEmpty(
            $duplicates,
            "Duplicate MCP IDs found:\n" . implode("\n", $messages)
        );
    }

    // ── Test 12: MCP schema bypass annotations ────────────────────────────

    public function testMcpSchemaBypassAnnotations(): void
    {
        $dirs = [
            self::$projectRoot . '/core/src',
            self::$nodeDir,
        ];

        // Schema-enabled MCP classes that MUST use callValidatedJson() or have @mcp-schema-bypass
        $schemaEnabledPattern = '/VectorMemoryMcp::call\(|VectorTaskMcp::call\(/';
        $validatedPattern = '/callValidatedJson|callJson/';

        $violations = [];

        foreach ($dirs as $dir) {
            $files = self::phpFiles($dir);
            foreach ($files as $file) {
                $lines = file($file, FILE_IGNORE_NEW_LINES);
                if ($lines === false) {
                    continue;
                }
                $relative = str_replace(self::$projectRoot . '/', '', $file);

                foreach ($lines as $lineNum => $lineContent) {
                    // Skip if line uses validated variants
                    if (preg_match($validatedPattern, $lineContent)) {
                        continue;
                    }
                    // Check if line has raw ::call() on schema-enabled MCP
                    if (!preg_match($schemaEnabledPattern, $lineContent)) {
                        continue;
                    }
                    // Look for @mcp-schema-bypass in previous 3 lines
                    $hasBypass = false;
                    for ($offset = 1; $offset <= 3; $offset++) {
                        $prevLine = $lineNum - $offset;
                        if ($prevLine >= 0 && str_contains($lines[$prevLine], '@mcp-schema-bypass')) {
                            $hasBypass = true;
                            break;
                        }
                    }
                    if (!$hasBypass) {
                        $violations[] = sprintf(
                            '%s:%d — raw ::call() on schema-enabled MCP without @mcp-schema-bypass',
                            $relative,
                            $lineNum + 1
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "MCP schema bypass violations (use callValidatedJson() or add @mcp-schema-bypass):\n"
            . implode("\n", $violations)
        );
    }

    // ── Test 13: MCP file manifest (guard against untracked required files) ──

    /**
     * Asserts the exact set of expected MCP PHP files in node/Mcp/.
     *
     * This is a recurrence guard: if a required MCP file is missing
     * (e.g. excluded via .git/info/exclude or .gitignore), this test fails
     * with a diagnostic message pointing to the root cause.
     *
     * Update $expected when adding or removing MCP classes.
     */
    public function testMcpFileManifestIsComplete(): void
    {
        $mcpDir = self::$nodeDir . '/Mcp';
        $files = array_map('basename', self::phpFiles($mcpDir));
        sort($files);

        $expected = [
            'Context7Mcp.php',
            'GithubMcp.php',
            'LaravelBoostMcp.php',
            'SequentialThinkingMcp.php',
            'VectorMemoryMcp.php',
            'VectorTaskMcp.php',
        ];

        $this->assertSame(
            $expected,
            $files,
            "MCP file manifest mismatch.\n"
            . "Expected: " . implode(', ', $expected) . "\n"
            . "Actual:   " . implode(', ', $files) . "\n"
            . "If you added/removed an MCP class, update this test manifest.\n"
            . "If files are unexpectedly missing, check .git/info/exclude and .gitignore — "
            . "required source files MUST be tracked in git."
        );
    }

    // ── Test 14: pins.json structure ──────────────────────────────────────

    public function testPinsJsonStructure(): void
    {
        $pinsFile = self::$projectRoot . '/pins.json';
        $this->assertFileExists($pinsFile, 'pins.json not found in project root');

        $content = file_get_contents($pinsFile);
        $data = json_decode($content, true);
        $this->assertNotNull($data, 'pins.json is not valid JSON');

        // Must have _meta key
        $this->assertArrayHasKey('_meta', $data, 'pins.json missing _meta key');
        $this->assertArrayHasKey('description', $data['_meta'], 'pins.json _meta missing description');

        // Must have at least one package entry (not _meta)
        $packages = array_filter(
            array_keys($data),
            fn (string $key): bool => $key !== '_meta'
        );
        $this->assertNotEmpty($packages, 'pins.json has no package entries');

        // Each package must have a non-empty version string
        foreach ($packages as $pkg) {
            $this->assertIsString($data[$pkg], "pins.json[$pkg] must be a version string");
            $this->assertNotEmpty($data[$pkg], "pins.json[$pkg] version is empty");
            $this->assertMatchesRegularExpression(
                '/^\d+\.\d+\.\d+/',
                $data[$pkg],
                "pins.json[$pkg] = '{$data[$pkg]}' is not a valid semver"
            );
        }
    }
}
