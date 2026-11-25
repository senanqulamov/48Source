<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FeatureFlag;
use Database\Seeders\Support\Fixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceCatalogSeeder extends Seeder
{
    // Broad procurement taxonomy. Products below are real-world *types*, not real company offers.
    public const GROUPS = [
        'Construction materials' => [
            'Rebar & structural steel' => ['B500B reinforcing bar 12 mm', 'B500B reinforcing bar 16 mm', 'S235 steel angle 50×50', 'IPE 160 steel beam'],
            'Cement & concrete' => ['Portland cement CEM I 42.5R', 'Ready-mix concrete C25/30', 'Ready-mix concrete C30/37', 'Concrete plasticizer'],
            'Aggregates & masonry' => ['Washed sand 0–4 mm', 'Crushed stone 5–20 mm', 'Autoclaved aerated concrete block', 'Ceramic hollow brick'],
            'Waterproofing & insulation' => ['Bitumen waterproofing membrane 4 mm', 'XPS insulation board 50 mm', 'Rock wool board 100 mm', 'Vapor barrier film'],
            'Finishing materials' => ['Gypsum plaster 25 kg', 'Ceramic tile 60×60 cm', 'Acrylic primer 10 L', 'Interior latex paint 15 L'],
            'Timber & formwork' => ['Phenolic plywood 18 mm', 'Timber beam 50×100 mm', 'Reusable formwork panel', 'OSB/3 board 18 mm'],
        ],
        'Electrical & automation' => [
            'Cables & conductors' => ['Copper cable NYY 3×2.5 mm²', 'Copper cable NYY 5×6 mm²', 'Cat6 U/UTP cable 305 m', 'Flexible conduit 25 mm'],
            'Switchgear & protection' => ['Miniature circuit breaker C16', 'Residual current breaker 40 A', 'Distribution board 24 way', 'Surge protection type 2'],
            'Lighting' => ['LED panel 600×600 40 W', 'IP65 floodlight 100 W', 'Emergency exit luminaire', 'LED strip 24 V'],
            'Power equipment' => ['Online UPS 3 kVA', 'Diesel generator 50 kVA', 'Automatic transfer switch 100 A', 'Isolation transformer 10 kVA'],
            'Sensors & controls' => ['Industrial proximity sensor', 'PLC digital input module', 'Temperature transmitter PT100', 'DIN rail relay 24 V'],
            'Solar & energy' => ['Monocrystalline solar panel 550 W', 'Grid-tie inverter 10 kW', 'DC combiner box', 'Solar mounting rail'],
        ],
        'Mechanical & HVAC' => [
            'Ventilation' => ['Galvanized air duct 500×300', 'Axial fan 400 mm', 'Air diffuser 600×600', 'Fire damper EI60'],
            'Heating & cooling' => ['Wall-mounted split AC 12000 BTU', 'Heat pump 16 kW', 'Panel radiator 600×1000', 'Chiller circulation pump'],
            'Pipes & fittings' => ['PPR pipe PN20 32 mm', 'HDPE pipe PE100 110 mm', 'Elbow fitting 90° DN50', 'Flanged ball valve DN80'],
            'Pumps & compressors' => ['Centrifugal pump 5.5 kW', 'Submersible drainage pump', 'Air compressor 500 L/min', 'Pressure expansion vessel'],
            'Valves & instruments' => ['Pressure gauge 0–16 bar', 'Flow meter DN50', 'Butterfly valve DN100', 'Automatic air vent'],
            'Refrigeration' => ['Cold room evaporator', 'Refrigerant R410A cylinder', 'Copper tube 3/8 inch', 'Thermostatic expansion valve'],
        ],
        'IT & telecommunications' => [
            'Computers & displays' => ['Business laptop 14 inch i5 16 GB', 'Desktop workstation i7 32 GB', '27 inch IPS monitor', 'USB-C docking station'],
            'Servers & storage' => ['Rack server 2U 64 GB', 'Enterprise SSD 1.92 TB', 'NAS 8-bay enclosure', 'ECC DDR4 32 GB module'],
            'Network equipment' => ['Managed PoE switch 24-port', 'Wi-Fi 6 access point', 'Enterprise firewall appliance', 'SFP+ 10G transceiver'],
            'Peripherals' => ['Laser printer duplex A4', 'Document scanner ADF', 'Barcode scanner USB', 'Wireless keyboard and mouse'],
            'Telephony' => ['IP desk phone', 'SIP gateway 8 port', 'Conference speakerphone', 'Fiber patch panel 24 port'],
            'Software & licensing' => ['Endpoint security annual license', 'Backup software seat', 'Database support subscription', 'Helpdesk SaaS annual plan'],
        ],
        'Security & fire protection' => [
            'Video surveillance' => ['IP dome camera 4 MP', 'NVR 16 channel', 'Surveillance HDD 8 TB', 'Camera mounting bracket'],
            'Access control' => ['RFID door controller', 'Electromagnetic lock 280 kg', 'Access reader IP65', 'Exit push button'],
            'Fire detection' => ['Addressable smoke detector', 'Fire alarm control panel', 'Manual call point', 'Sounder beacon'],
            'Firefighting' => ['CO2 extinguisher 5 kg', 'Fire hose cabinet', 'Sprinkler head 68 °C', 'Fire hydrant valve DN65'],
            'PPE & safety' => ['Safety helmet EN397', 'Reflective high-visibility vest', 'Nitrile work gloves', 'Fall arrest harness'],
            'Emergency equipment' => ['Emergency evacuation sign', 'First-aid cabinet', 'Spill response kit', 'Portable gas detector'],
        ],
        'Office & furniture' => [
            'Office furniture' => ['Ergonomic swivel chair', 'Workstation desk 1600 mm', 'Steel filing cabinet', 'Meeting table 2400 mm'],
            'Paper & stationery' => ['A4 copier paper 80 gsm box', 'Ballpoint pen pack', 'Whiteboard marker set', 'Lever-arch file'],
            'Meeting equipment' => ['Interactive display 65 inch', 'Ceiling projector 4000 lm', 'Video conferencing camera', 'Flipchart easel'],
            'Cleaning supplies' => ['Neutral floor cleaner 5 L', 'Microfiber cloth pack', 'Liquid hand soap 5 L', 'Industrial bin liner pack'],
            'Breakroom supplies' => ['Electric kettle 2 L', 'Water dispenser', 'Coffee beans 1 kg', 'Disposable paper cup pack'],
            'Storage & shelving' => ['Steel archive shelving bay', 'Plastic storage box 60 L', 'Pallet rack beam', 'Label printer'],
        ],
        'Industrial equipment' => [
            'Power tools' => ['Cordless drill 18 V', 'Angle grinder 125 mm', 'Rotary hammer SDS+', 'Circular saw 185 mm'],
            'Welding & cutting' => ['MIG/MAG welding machine 250 A', 'Welding wire 1.0 mm spool', 'Plasma cutter 40 A', 'Welding mask auto-darkening'],
            'Lifting & handling' => ['Manual pallet jack 2.5 t', 'Electric chain hoist 1 t', 'Polyester lifting sling 3 t', 'Mobile scissor platform'],
            'Measuring instruments' => ['Digital multimeter TRMS', 'Laser distance meter 100 m', 'Digital caliper 150 mm', 'Thermal imaging camera'],
            'Machine components' => ['Deep groove ball bearing 6205', 'V-belt SPA 1250', 'Linear guide 20 mm', 'Industrial coupling DN80'],
            'Air & hydraulics' => ['Hydraulic hose DN12', 'Pneumatic cylinder 32 mm', 'Pressure regulator 1/2 inch', 'Hydraulic oil ISO VG46'],
        ],
        'Automotive & transport' => [
            'Fleet consumables' => ['Diesel engine oil 15W-40 20 L', 'Cabin air filter', 'Brake pads set', 'Heavy duty coolant 20 L'],
            'Tyres & wheels' => ['Commercial tyre 215/75 R16', 'Truck tyre 315/80 R22.5', 'Wheel balancing weight', 'Steel wheel rim'],
            'Battery & electrical' => ['Starter battery 12 V 100 Ah', 'Alternator 24 V', 'Vehicle fuse assortment', 'LED warning beacon'],
            'Workshop equipment' => ['Vehicle diagnostic scanner', 'Hydraulic trolley jack 3 t', 'Impact wrench 1/2 inch', 'Air hose reel'],
            'Loading equipment' => ['Cargo ratchet strap 5 t', 'Pallet 1200×800', 'Forklift battery charger', 'Loading dock bumper'],
            'Transport services' => ['Urban freight delivery trip', 'Regional linehaul trip', 'Cold-chain distribution trip', 'Container haulage trip'],
        ],
        'Healthcare & laboratory' => [
            'Medical consumables' => ['Nitrile examination gloves box', 'Sterile gauze swabs', 'Medical face mask box', 'Disposable syringe 5 mL'],
            'Diagnostic equipment' => ['Digital blood pressure monitor', 'Pulse oximeter fingertip', '12-lead ECG device', 'Portable ultrasound probe'],
            'Laboratory equipment' => ['Bench centrifuge 24 place', 'Micropipette 20–200 µL', 'Laboratory refrigerator', 'Digital microscope'],
            'Disinfection' => ['Surface disinfectant 5 L', 'Hand antiseptic 1 L', 'Autoclave indicator tape', 'Sharps disposal bin'],
            'Patient furniture' => ['Hospital overbed table', 'Examination couch', 'Patient wheelchair', 'Stainless instrument trolley'],
            'Laboratory consumables' => ['Blood collection tube pack', 'Petri dish pack', 'Microscope slides box', 'Pipette tip rack'],
        ],
        'Food & hospitality' => [
            'Dry food' => ['Wheat flour 25 kg', 'White rice 25 kg', 'Sunflower oil 5 L', 'Granulated sugar 25 kg'],
            'Cold chain food' => ['Frozen vegetables 10 kg', 'UHT milk 1 L box', 'Butter 82% 1 kg', 'Frozen chicken fillet 10 kg'],
            'Food service' => ['Stainless serving tray', 'Commercial food container', 'Paper takeaway box pack', 'Food-safe gloves box'],
            'Commercial kitchen' => ['Commercial convection oven', 'Dishwasher under-counter', 'Electric induction hob', 'Refrigerated display cabinet'],
            'Beverage supply' => ['Bottled drinking water 19 L', 'Black tea 1 kg', 'Coffee capsules pack', 'Fruit juice 1 L case'],
            'Hotel supplies' => ['White cotton towel 500 gsm', 'Cotton bed linen set', 'Guest amenity kit', 'Hotel room key card'],
        ],
        'Agriculture & environment' => [
            'Irrigation systems' => ['Drip irrigation tube 16 mm', 'Water filter 2 inch', 'Solenoid irrigation valve', 'Sprinkler 3/4 inch'],
            'Greenhouse equipment' => ['Greenhouse film 200 micron', 'Climate controller', 'Shade net 50%', 'Growing tray 72-cell'],
            'Fertilizers & soil' => ['NPK fertilizer 20-20-20', 'Agricultural lime 25 kg', 'Peat growing substrate 70 L', 'Soil test kit'],
            'Farm machinery' => ['Rotary tiller attachment', 'Sprayer pump 12 V', 'Brush cutter 2-stroke', 'Hydraulic tractor filter'],
            'Waste management' => ['Recycling container 240 L', 'Compactor bag pack', 'Biohazard bin 60 L', 'Waste sorting sign'],
            'Environmental testing' => ['Portable pH meter', 'Water turbidity meter', 'Air particulate monitor', 'Soil sampling auger'],
        ],
        'Professional services' => [
            'Engineering services' => ['Structural assessment service', 'Electrical design service', 'Mechanical commissioning service', 'Surveying and setting-out service'],
            'IT services' => ['Network installation service', 'Server maintenance service', 'Penetration testing service', 'Application support service'],
            'Facility services' => ['HVAC preventive maintenance visit', 'Building deep cleaning service', 'Elevator inspection service', 'Fire system inspection service'],
            'Transport & logistics' => ['Warehouse handling service', 'Customs brokerage service', 'Pallet distribution service', 'Freight forwarding service'],
            'Training & consulting' => ['Occupational safety training', 'Quality management training', 'Procurement process consulting', 'Cybersecurity awareness workshop'],
            'Testing & certification' => ['Concrete cube compression test', 'Cable insulation resistance test', 'Water quality test', 'ISO 9001 external audit service'],
        ],
    ];

    public function run(): void
    {
        $groups = 0;
        $subcategories = 0;
        $products = 0;
        foreach (self::GROUPS as $group => $subgroups) {
            $groupModel = Category::firstOrCreate(['slug' => Str::slug($group)], ['name' => $group]);
            $groups++;
            foreach ($subgroups as $name => $itemNames) {
                $slug = Str::slug($group.' '.$name);
                $child = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'parent_id' => $groupModel->id]);
                $subcategories++;
                foreach ($itemNames as $itemName) {
                    $sku = 'CAT-'.strtoupper(substr(sha1($group.'|'.$name.'|'.$itemName), 0, 12));
                    $unit = str_contains(mb_strtolower($itemName), 'service') || str_contains(mb_strtolower($itemName), 'trip') || str_contains(mb_strtolower($itemName), 'workshop') ? 'service' : 'pcs';
                    DB::table('products')->updateOrInsert(['sku' => $sku], Fixture::stamped([
                        'sku' => $sku, 'name' => $itemName, 'category_id' => $child->id,
                        'unit' => $unit, 'description' => $group.' / '.$name.'. Specification-based procurement item.',
                        'status' => 'active',
                    ]));
                    $products++;
                }
            }
        }
        foreach (FeatureFlag::DEFAULTS as $key => [$label, $on]) {
            FeatureFlag::firstOrCreate(['key' => $key], ['label' => $label, 'enabled' => $on]);
        }
        foreach (['AZN' => 'Azerbaijani manat', 'USD' => 'United States dollar', 'EUR' => 'Euro'] as $code => $name) {
            DB::table('currencies')->updateOrInsert(['code' => $code], Fixture::stamped(['code' => $code, 'name' => $name, 'active' => true]));
        }
        Fixture::note('Catalog', "{$groups} groups, {$subcategories} subcategories, {$products} real-world product types.");
    }
}
