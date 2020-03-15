<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPHtmlParser\Dom;
use App\Models\Country;
use App\Models\Entry;

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
        // get data
        $response = Http::get($this->sourceUrl);
        if (!$response->successful()) {
            $this->error('HTTP Error: '.$response->body());
        }
        // parse data
        $html = $response->body();
        $items = $this->parseData($html);
        // sync data
        $items = $this->syncCountries($items);
        $this->syncEntries($items);
    }
    
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
    
    public function syncEntries($items) {
        $latestEntriesIds = Entry::select(DB::raw('max(id) as id, country_id'))->groupBy('country_id')->get();
        $latestEntries = Entry::whereIn('id', $latestEntriesIds->pluck('id'))->get()->keyBy('country_id');
        
        $insertBatch = [];
        $now = now();
        foreach ($items as $item) {
            if (empty($item['country_id'])) continue;
            // don't insert if entry has same data
            $latestResult = $latestEntries->get($item['country_id']);
            if ($latestResult) {
                $latestHash = "hash-{$latestResult->country_id}-{$latestResult->cases}-{$latestResult->deaths}-{$latestResult->recovered}-{$latestResult->active}-{$latestResult->critical}";
                $currentHash = "hash-{$item['country_id']}-{$item['cases']}-{$item['deaths']}-{$item['recovered']}-{$item['active']}-{$item['critical']}";
                if ($latestHash == $currentHash) continue;
            }
            $insertBatch[] = [
                'created_at' => $now,
                'updated_at' => $now,
                'country_id' => $item['country_id'],
                'cases'      => $item['cases'],
                'deaths'     => $item['deaths'],
                'recovered'  => $item['recovered'],
                'active'     => $item['active'],
                'critical'   => $item['critical'],
            ];
        }
        Entry::insert($insertBatch);
    }
    
    public function syncCountries($items) {
        $countries = Country::get()->pluck('id', 'name')->all();
        foreach ($items as &$item) {
            if (!isset($countries[$item['country']])) {
                $newCountry = null;
                $newCountry = new Country;
                $newCountry->name = $item['country'];
                $newCountry->aliases = [];
                if ($newCountry->save()) {
                    $countries[$item['country']] = $newCountry->id;
                }
            }
            $item['country_id'] = $countries[$item['country']];
        }
        return $items;
    }
    
}
