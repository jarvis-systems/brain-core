<?php

declare(strict_types=1);

namespace BrainCore\Console\Commands;

use Bfg\Dto\Dto;
use BrainCore\Abstracts\ArchitectureAbstract;
use BrainCore\Architectures\ArchetypeArchitecture;
use BrainCore\Merger;
use BrainCore\Support\Brain;
use BrainCore\TomlBuilder;
use BrainCore\XmlBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ConvertCommand extends Command
{
    protected $signature = 'convert 
        {files : The file to read}
        {--variables= : Variables to use in the compilation}
        {--json : Output in JSON format}
        {--xml : Output in XML format}
        {--yaml : Output in YAML format}
        {--toml : Output in TOML format}
        {--meta : Output only meta information}
    ';

    protected $description = 'Compile the Brain configurations files';

    public function handle(): int
    {
        $cmdStart = microtime(true);

        $variables = $this->option('variables') ?? "{}";
        $variables = json_decode($variables, true) ?? [];
        if ($variables) {
            Brain::mergeVariables($variables);
        }
        $isXml = $this->option('xml');
        $isJson = $this->option('json');
        $isYaml = $this->option('yaml');
        $isToml = $this->option('toml');
        $isMeta = $this->option('meta');
        $isSetOption = $isXml || $isJson || $isYaml || $isToml || $isMeta;
        $format = ($isXml ? 'xml' : ($isJson ? 'json' : ($isYaml ? 'yaml' : ($isToml ? 'toml' : ($isMeta ? 'meta' : 'dump')))));
        $dumpFormat = null;
        $files = explode(" && ", $this->argument('files'));
        $result = [];

        $timings = ['parse' => 0, 'fromEmpty' => 0, 'merger' => 0, 'builder' => 0, 'other' => 0];

        foreach ($files as $file) {
            if (preg_match('/^(.*)::([a-z]+)$/', $file, $matches)) {
                $file = $matches[1];
                if (! $isMeta && ! $isSetOption) {
                    $dumpFormat = $format;
                    $format = $matches[2];
                }
            }
            $parseStart = microtime(true);
            /** @var class-string<Dto>|int $class */
            $class = $this->getClassPathByFile($file);
            $timings['parse'] += (microtime(true) - $parseStart) * 1000;

            if (is_int($class)) {
                if ($dumpFormat) {
                    $format = $dumpFormat;
                    $dumpFormat = null;
                }
                continue;
            }
            $classBasename = class_basename($class);
            $className = Str::of($class)
                ->replace("BrainNode\\", '')
                ->replace("\\", '_')
                ->snake()
                ->replace("__", '_')
                ->upper()
                ->trim()
                ->trim('_')
                ->toString();

            $groupName = Str::of($class)->upper()
                ->explode("\\", 2)
                ->last();

            if (
                Brain::getEnv($className . '_DISABLE')
                || Brain::getEnv($groupName . '_DISABLE')
            ) {
                if ($dumpFormat) {
                    $format = $dumpFormat;
                    $dumpFormat = null;
                }
                continue;
            }

            if (is_subclass_of($class, ArchitectureAbstract::class)) {
                if (
                    $class::disableByDefault()
                    && ! Brain::getEnv($className . '_ENABLE')
                ) {
                    if ($dumpFormat) {
                        $format = $dumpFormat;
                        $dumpFormat = null;
                    }
                    continue;
                }
            }

            $fromEmptyStart = microtime(true);

            $class::on('created', function (Dto $dto) use ($variables) {
                $dto->setMeta($variables);
                Brain::setCurrentCompileDto($dto);
            });
            $dto = $class::fromEmpty();
            $class::clearEvents();
            Brain::setCurrentCompileDto(null);
            $timings['fromEmpty'] += (microtime(true) - $fromEmptyStart) * 1000;

            $otherStart = microtime(true);
            $timings['other'] += (microtime(true) - $otherStart) * 1000;
            $defaultData = [
                'id' => Str::snake($classBasename, '-'),
                'file' => $file,
                'class' => $class,
                'meta' => $dto->getMeta(),
                'namespace' => $namespace = str_replace('\\' . $classBasename, '', $class),
                'namespaceType' => trim(str_replace(explode('\\', $class)[0], '', $namespace), '\\') ?: null,
                'classBasename' => $classBasename,
                'format' => $format,
                'structure' => null,
            ];

            if ($format !== 'meta') {
                $mergerStart = microtime(true);
                if ($dto instanceof ArchetypeArchitecture) {
                    $structure = Merger::from($dto);
                } else {
                    $structure = $dto->toArray();
                }
                $timings['merger'] += (microtime(true) - $mergerStart) * 1000;

                if ($format === 'xml') {
                    $builderStart = microtime(true);
                    $xmlOutput = XmlBuilder::from($structure);
                    $timings['builder'] += (microtime(true) - $builderStart) * 1000;
                    $xmlOutput = str_replace("\\", "\\\\", $xmlOutput);
                    $result[$file] = [
                        ...$defaultData,
                        'structure' => $xmlOutput,
                    ];
                } else if ($format === 'json') {
                    $result[$file] = [
                        ...$defaultData,
                        'structure' => $structure,
                    ];
                } elseif ($format === 'yaml') {
                    $result[$file] = [
                        ...$defaultData,
                        'structure' => Yaml::dump($structure, 512, 2, Yaml::DUMP_OBJECT_AS_MAP),
                    ];
                } elseif ($format === 'toml') {
                    $result[$file] = [
                        ...$defaultData,
                        'structure' => TomlBuilder::from($structure),
                    ];
                } else {
                    dump([
                        ...$defaultData,
                        'structure' => $structure,
                    ]);
                    $result = false;
                }
            } else {
                $result[$file] = $defaultData;
            }

            if ($dumpFormat) {
                $format = $dumpFormat;
                $dumpFormat = null;
            }
        }

        $cmdTime = (microtime(true) - $cmdStart) * 1000;

        if (getenv('BRAIN_PROFILE') === '1') {
            // Log profiling to stderr
            error_log(sprintf(
                "PROFILE: Total=%.2fms | Parse=%.2fms (%.1f%%) | fromEmpty=%.2fms (%.1f%%) | Merger=%.2fms (%.1f%%) | Builder=%.2fms (%.1f%%) | Other=%.2fms (%.1f%%)",
                $cmdTime,
                $timings['parse'], ($timings['parse'] / $cmdTime) * 100,
                $timings['fromEmpty'], ($timings['fromEmpty'] / $cmdTime) * 100,
                $timings['merger'], ($timings['merger'] / $cmdTime) * 100,
                $timings['builder'], ($timings['builder'] / $cmdTime) * 100,
                $timings['other'], ($timings['other'] / $cmdTime) * 100
            ));
        } else {
            if ($result) {
                echo json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            }
        }

        return OK;
    }

    /**
     * Get class path by file.
     *
     * @param  string  $file
     * @return string|int
     */
    protected function getClassPathByFile(string $file): string|int
    {
        $file = Brain::basePath($file);

        if (!file_exists($file) || !is_file($file)) {
            return ERROR;
        }

        $content = file_get_contents($file);
        preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/', $content, $namespaceMatches);
        preg_match('/\nclass\s+([a-zA-Z0-9_]+)\s*/', $content, $classMatches);

        if (isset($namespaceMatches[1]) && isset($classMatches[1])) {
            $class = $namespaceMatches[1] . '\\' . $classMatches[1];
            if (class_exists($class) && is_subclass_of($class,Dto::class)) {
                return $class;
            } else {
                return ERROR;
            }
        } else {
            return ERROR;
        }
    }
}

