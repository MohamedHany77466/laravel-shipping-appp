<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ShipmentTrackingController;

class AutoTransitShipments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'shipments:auto-transit';

    /**
     * The console command description.
     */
    protected $description = 'تحويل الشحنات تلقائياً إلى حالة "في الطريق" في تاريخ السفر';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new ShipmentTrackingController();
        $result = $controller->autoTransitShipments();
        
        $data = $result->getData();
        $this->info($data->message);
        
        return 0;
    }
}