<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PHPHtmlParser\Dom;

class SyncCommand extends Command
{
    protected $signature = 'sync';
    protected $description = 'Sync data from external source worldometers';
    public $sourceUrl = 'https://www.worldometers.info/coronavirus/';
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {
        $response = Http::get($this->sourceUrl);
        if (!$response->successful()) {
            $this->error('HTTP Error: '.$response->body());
        }
        $html = $response->body();
        $items = $this->parseData($html);
        dd($items);
    }
    
    // Credits to: https://github.com/BaseMax/CoronaVirusOutbreakAPI
    public function parseData($html) {
        if ($html == '' || $html == null) return [];
        $dom = new Dom;
        $dom->load($html);
        $items = [];
        foreach ($dom->find('table') as $table) {
            foreach ($table->find('tr') as $row) {
                if (count($row->find('td')) == 0) continue;
                $cols = [];
                foreach ($row->find('td') as $col) {
                    $cols[] = trim(strip_tags($col->innerHtml));
                }
                array_push($items, $this->prepareEntry($cols));
            }
        }
        return $items;
    }
    
    // Credits to: https://github.com/BaseMax/CoronaVirusOutbreakAPI
    public function prepareEntry($cols) {
        $item = [
            'country'   => $cols[0],
            'cases'     => $cols[1],
            'deaths'    => $cols[3],
            'recovered' => $cols[5],
            'active'    => $cols[6],
            'critical'  => $cols[7],
        ];
        if (strpos(strtolower($item['country']), 'total') !== false) {
            $item['country'] = 'global';
        }
        $item['cases']     = (int) preg_replace('/[^\d]/', '', $item['cases'])     ?: 0;
        $item['deaths']    = (int) preg_replace('/[^\d]/', '', $item['deaths'])    ?: 0;
        $item['recovered'] = (int) preg_replace('/[^\d]/', '', $item['recovered']) ?: 0;
        $item['active']    = (int) preg_replace('/[^\d]/', '', $item['active'])    ?: 0;
        $item['critical']  = (int) preg_replace('/[^\d]/', '', $item['critical'])  ?: 0;
        return $item;
    }
    
}
