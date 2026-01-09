<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $user = $request->user();
        $user->theme = $data['theme'];
        $user->save();

        return response()->json(['ok' => true]);
    }
}
