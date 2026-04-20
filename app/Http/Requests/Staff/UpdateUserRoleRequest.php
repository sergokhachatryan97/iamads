<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->guard('staff')->user();
        return $user && ($user->hasRole('super_admin') || $user->hasPermissionTo('users.edit', 'staff'));
    }


    public function rules(): array
    {
        $roleNames = Role::where('guard_name', 'staff')
            ->pluck('name')
            ->all();

        return [
            'role' => ['required', 'string', Rule::in($roleNames)],
        ];
    }
}
