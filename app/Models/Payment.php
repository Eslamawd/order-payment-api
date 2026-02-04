<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_number',
        'amount',
        'currency',
        'payment_method',
        'gateway_name',
        'gateway_reference',
        'status',
        'gateway_response'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function markAsSuccessful(string $gatewayReference, array $response = []): void
    {
        $this->update([
            'status' => 'successful',
            'gateway_reference' => $gatewayReference,
            'gateway_response' => $response,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(array $response = []): void
    {
        $this->update([
            'status' => 'failed',
            'gateway_response' => $response,
            'processed_at' => now(),
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $payment->payment_number = 'PAY-' . strtoupper(uniqid());
        });
    }
}