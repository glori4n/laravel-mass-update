<?php

namespace App\Console\Commands;

use App\ActionData;
use App\Console\Commands\Seed\Coach;
use App\Godfather;
use App\User;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class MassUpdateGodfather extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:massgodfather {file : File to import. CSV assumed with \',\' separator}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    /**
     * @var Client
     */
    private $client;
    /**
     * @var array
     */
    private $headers;
    /**
     * @var \Illuminate\Config\Repository
     */
    private $routes;
    private $env;
    private $token;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new Client();

        $this->headers = [
            'headers' => [
                'TokenCrypt' => config('actiondata.tokencrypt')
            ]
        ];

        $this->token = config('actiondata.token');
        $this->env = config('actiondata.env');
        $this->routes = config('actiondata.routes');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $file = $this->argument('file');

        $csvdata = collect(array_map('str_getcsv', file($file)));
        $head = head($csvdata->toArray(true));
        unset($csvdata[0]);
        $x = $csvdata->values()->map (function ($item) use ($head){
            return [
                $head[0] => $item[0],
                $head[1] => $item[1],
                $head[2] => $item[2],
                $head[3] => $item[3]
            ];

        })->toArray();

        foreach ($x as $entries) {
            DB::beginTransaction();

            $godfather = Godfather::where('code', $entries[$head[0]])->first();
            $coach = User::where('email', $entries[$head[3]])->first()->id;

            $godfather->transferTo($coach);

            $response = $this->updateCoachForGodfather($godfather);

            if (in_array($response['status'], ['fail', 'error'], true)) {
                DB::rollBack();
                $processed['fail'][] = [
                    'id'   => $godfather->id,
                    'code' => $godfather->code
                ];
                logger(json_encode($response));
            }

            DB::commit();
        }


    }

    public function updateCoachForGodfather(Godfather $godfather)
    {
        $url = config('actiondata.change_ca_pad_'.$this->env);

        $this->headers['form_params'] = [
            'codigo' => $godfather->code,
            'email_ca' => $godfather->coach->email,
            'usuario' => auth()->user()->email
        ];

        return json_decode($this->client->post($url, $this->headers)->getBody()->getContents(), true);
    }
}
