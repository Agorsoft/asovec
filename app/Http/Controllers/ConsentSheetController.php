<?php

namespace App\Http\Controllers;

use App\Models\Association;
use App\Models\Member;
use Illuminate\Contracts\View\View;

class ConsentSheetController extends Controller
{
    public function show(Member $member): View
    {
        $association = Association::first();

        return view('documents.consent.ConsentSheet', compact('member', 'association'));
    }
}
