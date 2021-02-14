<?php

namespace App\Command;

use App\Service\HashcodeResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class HandleGoogleCode extends Command
{
    protected static $defaultName = 'hashcode:resolve';

    private HashcodeResolver $service;
    private string $pathDir;
    private int $totalScore = 0;
    private array $result = [];
    private int $countSlides = 0;

    public function __construct(HashcodeResolver $service, KernelInterface $kernel)
    {
        $this->service = $service;
        $this->pathDir = $kernel->getProjectDir().'/input/';


        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Google hashcode 2019 task resolver');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $filePath = $this->pathDir.'a_example.txt';
//        $filePath = $this->pathDir.'b_lovely_landscapes.txt';
        $filePath = $this->pathDir.'c_memorable_moments.txt';
        $fileHandle = fopen($filePath, 'r');

        // get count photos
        $countPhotos = (int) $this->unpack(fgets($fileHandle))[0];
        $photos = new \SplFixedArray($countPhotos);
        $hPhotos = new \SplFixedArray($countPhotos);
        $vPhotos = new \SplFixedArray($countPhotos);

        // init all photos by separate groups H and V
        for ($id = 0; $id < $countPhotos; $id++) {
            $photo = $this->unpack(fgets($fileHandle));

            $tags = array_splice($photo, 2);
            if($photo[0] === 'H') {
                $hPhotos[$id] = [
                    'id' => $id,
//                'orientation' => $photo[0],
                    // optimisation with get count
                    'count_tags' => (int) $photo[1],
                    'tags' => $tags,
                    'processing_score' => (int) $photo[1]
                ];
            } else {
                continue;
                $vPhotos[$id] = [
                    'id' => $id,
//                'orientation' => $photo[0],
                    // optimisation with get count
                    'count_tags' => (int) $photo[1],
                    'tags' => $tags,
                    'processing_score' => (int) $photo[1]
                ];
            }
        }

        $hPhotos = $this->resort($hPhotos->toArray());

        $this->processHPhotos($hPhotos);

        $output->writeln("Count photos is: $countPhotos");
        $output->writeln("Count slides is: $this->countSlides");
        $output->writeln("Total score is: $this->totalScore");

        fclose($fileHandle);

        return Command::SUCCESS;
    }

    private function unpack(string $line): array
    {
        return explode(' ', trim($line));
    }

    private function resort(array $array)
    {
        usort($array, function ($a, $b) {
            $aScore = $a['processing_score'] ?? 0;
            $bScore = $b['processing_score'] ?? 0;
            return $bScore <=> $aScore;
        });

        return $array;
    }

    private function processHPhotos(array $photos)
    {
        $photos = array_filter($photos, function ($value) {
           return !is_null($value);
        });

        while (count($photos) > 0) {
            $photo = array_shift($photos);


            if (count($this->result) !== 0) {
                $this->totalScore += $this->getScore($this->result[array_key_last($this->result)], $photo);
            }
            $this->result[] = $photo;
            $this->countSlides++;
            $this->recalcProcessScore($this->result[array_key_last($this->result)], $photos);
        }
    }

    private function getScore($previous, $current): int
    {
        if(is_null($previous) || !array_key_exists('tags', $previous)) {
            $previous['tags'] = [];
        }

        if(is_null($current) || !array_key_exists('tags', $current)) {
            $current['tags'] = [];
        }

        return min(
            count(array_intersect($previous['tags'], $current['tags'])),
            count(array_diff($previous['tags'], $current['tags'])),
            count(array_diff($current['tags'], $previous['tags']))
        );
    }

    private function recalcProcessScore($last, array &$photos): void
    {
        foreach ($photos as $key => $photo) {
            $photos[$key]['processing_score'] = $this->getScore($last, $photo);
        }

        $photos = $this->resort($photos);
    }

}