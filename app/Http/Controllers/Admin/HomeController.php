<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;

/**
 * Admin entry controller.
 *
 * @see routes/web.php
 * @see routes/admin.php
 */
class HomeController extends BaseAdminController
{
    public function index(): RedirectResponse
    {
        return redirect('/admin');
    }
}
