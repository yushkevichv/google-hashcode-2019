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
        ini_set('memory_limit', -1);
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
//        $filePath = $this->pathDir.'d_pet_pictures.txt';
//        $filePath = $this->pathDir.'e_shiny_selfies.txt';
        $fileHandle = fopen($filePath, 'r');

        // get count photos
        $countPhotos = (int) $this->unpack(fgets($fileHandle))[0];
        $hPhotos = new \SplFixedArray($countPhotos);
        $vPhotos = new \SplFixedArray($countPhotos);

        // init all photos by separate groups H and V
        for ($id = 0; $id < $countPhotos; $id++) {
            $photo = $this->unpack(fgets($fileHandle));

            $tags = array_splice($photo, 2);
            if ($photo[0] === 'H') {
                $hPhotos[$id] = [
                    'id' => $id,
//                'orientation' => $photo[0],
                    // optimisation with get count
                    'count_tags' => (int) $photo[1],
                    'tags' => $tags,
                    'processing_score' => (int) $photo[1]
                ];
            } else {
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

        $output->writeln("Start process HPhotos");

        $this->processHPhotos($hPhotos);
        $output->writeln("Start process VPhotos");
        $this->processsVPhotos($vPhotos->toArray());

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
            // @todo it is correct for accuracy, but slow. At big files need to another algo
            $this->recalcProcessScore($this->result[array_key_last($this->result)], $photos);
        }
    }

    private function getScore($previous, $current): int
    {
        if (is_null($previous) || !array_key_exists('tags', $previous)) {
            $previous['tags'] = [];
        }

        if (is_null($current) || !array_key_exists('tags', $current)) {
            $current['tags'] = [];
        }

        return min(
            count(array_intersect($previous['tags'], $current['tags'])),
            count(array_diff($previous['tags'], $current['tags'])),
            count(array_diff($current['tags'], $previous['tags']))
        );
    }

    /**
     * Good works for a, b (?), c. But bad for d,e. Need another solution for last
     */
    private function recalcProcessScore($last, array &$photos): void
    {
        foreach ($photos as $key => $photo) {
            $photos[$key]['processing_score'] = $this->getScore($last, $photo);
        }

        $photos = $this->resort($photos);
    }

    private function processsVPhotos(array $photos)
    {
        $photos = array_filter($photos, function ($value) {
            return !is_null($value);
        });

        usort($photos, function ($a, $b) {
            return $b['count_tags'] <=> $a['count_tags'];
        });

        $slides = [];
        while (count($photos) > 1) {
            $photo1 = array_shift($photos);
            $photo2 = array_pop($photos);
            $tags = array_unique(array_merge($photo1['tags'], $photo2['tags']));

            $slides[] = [
                'photo1' => $photo1['id'],
                'photo2' => $photo2['id'],
                'tags' => $tags,
                'count_tags' => count($tags),
                'processing_score' => count($tags)
            ];
        }
        if (count($photos) > 0) {
            $slides[] = [
                'photo1' => $photos[0]['id'],
                'photo2' => null,
                'tags' => $photos[0]['tags'],
                'count_tags' => count($photos[0]['tags']),
                'processing_score' => count($photos[0]['tags'])
            ];
        }

        $slides = $this->resort($slides);
        $this->processHPhotos($slides);
    }

}