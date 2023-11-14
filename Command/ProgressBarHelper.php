<?php

namespace Brevo\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProgressBarHelper
{
    public static function createProgressBar(OutputInterface $output, int $count): ProgressBar
    {
        $progressBar = new ProgressBar($output, $count);
        ProgressBar::setFormatDefinition('custom', '%message% | %bar% | %current%/%max% (%elapsed:6s%/%estimated:-6s%) -> %percent%% (%memory:6s%)');
        $progressBar->setFormat('custom');
        $progressBar->setBarCharacter('<fg=green>◆</>');
        $progressBar->setEmptyBarCharacter("<fg=red>◆</>");
        $progressBar->setProgressCharacter("<fg=white>◆</>");

        return $progressBar;
    }
}
