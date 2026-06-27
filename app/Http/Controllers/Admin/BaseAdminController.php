<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Base controller for custom admin HTTP actions.
 *
 * MVC mapping:
 * - Models      → app/Models/
 * - Controllers → app/Http/Controllers/Admin/  +  app/Filament/Resources/
 * - Views       → resources/views/admin/{section}/
 * - Routes      → routes/web.php  +  routes/admin.php
 */
abstract class BaseAdminController extends Controller
{
    //
}
