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
use Symfony\Component\Validator\Constraints\DateTime;

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

    public function __construct(array $untrackedTags = []) {
        $this->untrackedTags = $untrackedTags;

        parent::__construct();
    }
    
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

    private function getFormattedTags(string $tags) {

        $tag_translator = self::TAG_TRANSLATORS;
        $formattedTags = [
            'subcription_status' => '',
            'has_downloaded_free_product_status' => '',
            'has_downloaded_iap_product_status' => '',
        ];

        // we reorganise the tags
        if ($tags) {
            
            $tags = explode('|', $tags);
            foreach ($tags as $tag) {
                // if unknown tag then write it in log
                if (!array_key_exists($tag, $tag_translator)) {
                    if (!array_key_exists($tag, $this->untrackedTags)) {
                        $this->untrackedTags[$tag] = 1;
                    } else {
                        $this->untrackedTags[$tag]++;
                    }
                } else {
                    // not_downloaded_free_product is in two categories even if never found in the data provided
                    if ($tag == 'not_downloaded_free_product') {
                        $formattedTags = [
                            'subcription_status' => '',
                            'has_downloaded_free_product_status' => 'not_downloaded_free_product',
                            'has_downloaded_iap_product_status' => 'not_downloaded_free_product',
                        ];
                    }
                    $formattedTags[$tag_translator[$tag]] = $tag;
                }
            }
        }

        return $formattedTags;
    }

    private function checkDataTypes($line, $appCodesArray) 
    {
        $appCodesColumn = 0;
        $deviceTokenColumn = 1;
        $tokensStatusColumn = 2;
        $tagColumn = 3;

        if (empty($line)) {
            return 0;
        } else {
            if (  !is_string($line[$appCodesColumn])
               || !array_key_exists($line[$appCodesColumn], $appCodesArray)
               || !is_string($line[$deviceTokenColumn])
               || ! (is_bool(intval($line[$tokensStatusColumn])) || in_array(intval($line[$tokensStatusColumn]), [0,1]))
               || !is_string($line[$tokensStatusColumn])) {

               return 0;               
            } else {
                return 1;
            }
        }

    }
    

    private function convertMemory(int $size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();
        $finder = new Finder();
        $filesystem->remove($finder->files()->in('public/Formatted/'));
        $execStart = new \DateTime('now');

        $columns = self::COLUMNS;
        $tag_translator = self::TAG_TRANSLATORS;
        $parsedArray = [];
        
        $appCodesColumn = 0;
        $deviceTokenColumn = 1;
        $tokensStatusColumn = 2;
        $tagColumn = 3;
                
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
            $appCodesArray = self::appCodesFilesToArray();

            foreach ($fileAsArray as $key => $line) {

                $formattedTags = self::getFormattedTags($line[$tagColumn]);

                
                if(!self::checkDataTypes($line, $appCodesArray)) {
                    continue;
                };
            
                $parsedArray[] = [
                    $id,
                    $appCodesArray[$line[$appCodesColumn]],
                    $line[$deviceTokenColumn],
                    $line[$tokensStatusColumn],
                    $formattedTags['subcription_status'],
                    $formattedTags['has_downloaded_free_product_status'],
                    $formattedTags['has_downloaded_iap_product_status'],
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
        foreach  ($this->untrackedTags as $key => $value) {
            
            $io->note('ignored tag :' .$key.' '.$value.' occurences');
        }
    
        $execEnd = new \DateTime('now');
        $scriptDuration = $execStart->diff($execEnd);
        $scriptDuration = $scriptDuration->s.'s '.$scriptDuration->f.'ms';
        $io->note('used memory :' .$usedMemory);
        $io->note('time duration :' .$scriptDuration);

        $io->success('The data was well parsed');

        return Command::SUCCESS;
    }
}
