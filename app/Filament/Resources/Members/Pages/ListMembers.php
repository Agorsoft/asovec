<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberType;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ($this->activeTab ?? 'socios') !== 'no-socios'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'socios' => Tab::make('Socios')
                ->badge(fn () => Member::where('member_type', MemberType::Socio->value)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('member_type', MemberType::Socio->value)),
            'no-socios' => Tab::make('No Socios')
                ->badge(fn () => Member::where('member_type', MemberType::NoSocio->value)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('member_type', MemberType::NoSocio->value)),
        ];
    }
}
