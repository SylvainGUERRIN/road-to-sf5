<?php


namespace App\Command;


use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;

class StepInfoCommand extends Command
{
    protected static $defaultName = 'app:step:info';

    private $cache;

    /**
     * StepInfoCommand constructor.
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /*$process = new Process(['git','tag','-1','--points-at','HEAD']);
        $process->mustRun();
        $output->write($process->getOutput());*/

        $step = $this->cache->get('app.current_step', function ($item){
            $process = new Process(['git','tag','-1','--points-at','HEAD']);
            $process->mustRun();
            $item->expiresAfter(30);
            return $process->getOutput();
        });
        $output->writeln($step);

        return 0;
    }
}
