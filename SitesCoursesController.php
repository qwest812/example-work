<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\ArrayToXml\ArrayToXml;

/**
 * Class SitesCoursesController
 * @package App\Http\Controllers\Web
 */
class SitesCoursesController extends Controller
{

    public function export($site, Request $request)
    {
        $modelName = 'App\Lib\ExportRates\\' . ucfirst($site);
        if(!class_exists($modelName))
            abort('404, \'Page not found\'');

        $cursObj = new $modelName($request);
        return $cursObj->view();
    }
}
