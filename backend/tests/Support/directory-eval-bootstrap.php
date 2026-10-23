<?php

use Symfony\Component\Process\Process;

/** Test-only baseline: exact former production classes, never overwrite the working tree. */
$loader = require dirname(__DIR__, 2).'/vendor/autoload.php';

if (getenv('ALQUIVO_AI_EVAL_PHASE') === 'directory-baseline-20261022') {
    if (getenv('ALQUIVO_LIVE_PRODUCTION_EVAL') !== 'YES') {
        throw new RuntimeException('The historical baseline requires explicit synthetic evaluation opt-in.');
    }
    $revision = 'b451dba7d495fb5aa628bb65dd2ae7b42a64cc89';
    $directory = sys_get_temp_dir().'/alquivo-ai-directory-baseline-'.$revision;
    if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
        throw new RuntimeException('Could not create isolated code snapshot.');
    }
    $classes = [];
    foreach (['AssistantOrchestrator', 'AssistantConversationContext', 'AssistantLeasingQueries', 'PortfolioAssistantTools', 'ToolRegistry'] as $class) {
        $path = 'backend/app/Domain/Assistant/Services/'.$class.'.php';
        $process = new Process(['git', 'show', $revision.':'.$path], dirname(__DIR__, 3));
        $source = $process->mustRun()->getOutput();
        $target = $directory.'/'.$class.'.php';
        if (file_put_contents($target, $source) !== strlen($source)) {
            throw new RuntimeException('Incomplete code snapshot.');
        }
        $classes['App\\Domain\\Assistant\\Services\\'.$class] = $target;
    }
    $loader->addClassMap($classes);
}
