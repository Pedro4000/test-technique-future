<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:parser',
    description: 'parse the third party data',
)]
class ParserCommand extends Command
{
    
    
    CONST TAG_TRANSLATORS = [
        'active_subscriber' => 'subcription_status',
        'expired_subscriber' => 'subcription_status',
        'never_subscribed' => 'subcription_status',
        'subscription_unknown' => 'subcription_status',
        'has_downloaded_free_product' => 'has_downloaded_free_product_status',
        'not_downloaded_free_product' => ['has_downloaded_free_product_status', 'has_downloaded_iap_product_status'],
        'downloaded_free_product_unknown' => 'has_downloaded_free_product_status',
        'has_downloaded_iap_product' => 'has_downloaded_iap_product_status',
        'downloaded_iap_product_unknown' => 'has_downloaded_iap_product_status',
    ];
    CONST COLUMNS = [
        'id', // autoincremented integer
        'appCode', //  string => The current app code should be mapped to an app code found within the appCodes.ini file
        'deviceId', // string (deviceToken in third party files)
        'contactable', //   boolean => 0 or 1 (sometimes referred to as 'deviceTokenStatus' in the third-party files).
        'subscription_status',
        'has_downloaded_free_product_status',
        'has_downloaded_iap_product_status',
    ];
    
    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function appCodesFilesToArray() {

        $finder = new Finder();
        $appCodesArray = [];
        //$contents = $file->getContents();
        $finder->files()->in('public/Data/parser_test/parser_test/')->name('appCodes.ini');
        foreach ($finder as $appCodesFiles) {
            $contents = $appCodesFiles->getContents();
            $appCodes = explode("\n", $contents);
            array_shift($appCodes);
            array_pop($appCodes);
            foreach ($appCodes as $codeLine) {
                $values = explode('=', $codeLine);
                $appCodesArray[trim(trim($values[1]), '"')] = trim($values[0]);
            }
        }
        unset($appCodes);
        
        return $appCodesArray;
        
    }

    function convertMemory($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();
        $finder = new Finder();
        $filesystem->remove($finder->files()->in('public/Formatted/'));

        $columns = self::COLUMNS;
        $tag_translator = self::TAG_TRANSLATORS;
        $parsedArray = [];
        
        $appCodesColumn = 0;
        $deviceTokenColumn = 1;
        $tokensStatusColumn = 2;
        $tagColumn = 3;
        
        $untrackedTags = [];
        
        //$contents = $file->getContents();
        $appCodesArray = self::appCodesFilesToArray();

        $filesCounter = 0;
        $finder->files()->in('public/Data/parser_test/parser_test/')->notName('appCodes.ini');
        
        
        foreach ($finder as $file) {
            
            $filesCounter++;
            $id = 0;
            $contents = $file->getContents();
            $fileAsArray = explode("\n", $contents);
            foreach ($fileAsArray as &$line) {
                $line = str_getcsv($line);
            }
            array_shift($fileAsArray);

            $parsedArray[] = $columns;

            foreach ($fileAsArray as $key => $line) {

                $tagsFormatted = [
                    'subcription_status' => '',
                    'has_downloaded_free_product_status' => '',
                    'has_downloaded_iap_product_status' => '',
                ];

                // we reorganise the tags
                if ($line[$tagColumn]) {
                    
                    $tags = explode('|', $line[$tagColumn]);
                    foreach ($tags as $tag) {
                        // if unknown tag then write it in log
                        if (!array_key_exists($tag, $tag_translator)) {
                            if (!array_key_exists($tag, $untrackedTags)) {
                                $untrackedTags[$tag] = 1;
                            } else {
                                $untrackedTags[$tag]++;
                            }
                        } else {
                            if ($tag == 'not_downloaded_free_product') {
                                $tagsFormatted = [
                                    'subcription_status' => '',
                                    'has_downloaded_free_product_status' => 'not_downloaded_free_product',
                                    'has_downloaded_iap_product_status' => 'not_downloaded_free_product',
                                ];
                            }
                            $tagsFormatted[$tag_translator[$tag]] = $tag;
                        }
                    }
                }

                
                $parsedArray[] = [
                    $id,
                    $appCodesArray[$line[$appCodesColumn]],
                    $line[$deviceTokenColumn],
                    $line[$tokensStatusColumn],
                    $tagsFormatted['subcription_status'],
                    $tagsFormatted['has_downloaded_free_product_status'],
                    $tagsFormatted['has_downloaded_iap_product_status'],
                ];

                
                $id++;
            }
            
            
            $fileName = $file->getFilenameWithoutExtension();
            $csvFile = fopen('public/Formatted/'.$fileName.'.csv', 'w');
            foreach ($parsedArray as $line) {
                fputcsv($csvFile, $line);
            };
            fclose($csvFile);
            unset($parsedArray);
            //next file
        }
        $usedMemory = self::convertMemory(memory_get_usage());

        $io = new SymfonyStyle($input, $output);
        // we echo the ignored tags
        foreach  ($untrackedTags as $key => $value) {
            
            $io->note('ignored tag :' .$key.' '.$value.' occurences');
        }
    
        $io->note('used memory :' .$usedMemory);

        $io->success('The data was well parsed');

        return Command::SUCCESS;
    }
}
