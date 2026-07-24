<?php

namespace App\Console\Commands;

use App\Services\ChatService;
use Illuminate\Console\Command;

class PruneChat extends Command
{
    protected $signature = 'chat:prune';

    protected $description = 'Hapus pesan chat yang lebih dari 30 menit (fana).';

    public function handle(ChatService $chat): int
    {
        $count = $chat->prune();
        $this->info("Menghapus {$count} pesan chat kedaluwarsa.");

        return self::SUCCESS;
    }
}
