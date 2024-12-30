<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;

class DownloadCenterController extends Controller
{
    public function index()
    {
        return Inertia::render('DownloadCenter/DownloadCenter');
    }
}
