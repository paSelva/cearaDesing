<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Edition;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, Category $category): bool
    {
        return $user->role === Role::Admin
            && $category->edition()->where('year', Edition::YEAR)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Category $category): bool
    {
        return false;
    }

    public function delete(User $user, Category $category): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
