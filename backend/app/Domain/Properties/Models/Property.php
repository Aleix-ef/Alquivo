<?php

namespace App\Domain\Properties\Models;

use App\Domain\Attention\Models\Issue;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'portfolio_id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'valuation_date' => 'date',
            'purchase_price' => 'decimal:2', 'acquisition_costs' => 'decimal:2',
            'current_value' => 'decimal:2', 'outstanding_debt' => 'decimal:2',
        ];
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function photos()
    {
        return $this->hasMany(PropertyPhoto::class)->orderByDesc('is_cover')->orderBy('sort_order');
    }

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function valuations()
    {
        return $this->hasMany(PropertyValuation::class)->orderByDesc('valued_at');
    }
}
