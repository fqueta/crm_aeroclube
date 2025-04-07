<?php

namespace App\Jobs;

use App\Http\Controllers\MatriculasController;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendZapsingJoub implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $tm;
    public function __construct($tm)
    {
        $this->tm = $tm;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $jobLogger = Log::channel('jobs');
        $jobLogger->info('Iniciando o token matricula: '.$this->tm.'.');

        try {
            // Lógica do Job
            $ret = (new MatriculasController)->send_to_zapSing($this->tm);
            $jobLogger->info('EnvioZapsingJob token matricula: '.$this->tm.' está processando...',$ret);
        } catch (\Exception $e) {
            $jobLogger->error('Erro no EnvioZapsingJob: ' . $e->getMessage());
        }
    }
}
