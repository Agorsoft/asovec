<?php

namespace App\Http\Controllers;

use App\Models\Association;
use Illuminate\View\View;

class AssociationSepaMandateController extends Controller
{
    public function __invoke(Association $association): View
    {
        $creditorLogoUrl = null;

        if ($association->logo_path) {
            $creditorLogoUrl = asset('storage/'.ltrim($association->logo_path, '/'));
        }

        return view('associations.sepa-mandate', [
            'creditorLogoUrl' => $creditorLogoUrl,
            'creditorName' => $association->name,
            'creditorTaxId' => $association->tax_id,
            'creditorIdentifier' => $association->creditor_identifier,
            'creditorAddress' => $association->address,
            'creditorPostalCode' => $association->postal_code,
            'creditorCity' => $association->city,
            'creditorProvince' => $association->province,
            'creditorCountry' => $association->country,
            'issuedAt' => now(),
        ]);
    }
}
