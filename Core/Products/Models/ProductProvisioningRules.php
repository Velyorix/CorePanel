<?php

namespace Core\Products\Models;

use Database\Factories\ProductProvisioningRulesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductProvisioningRules extends Model
{
    /** @use HasFactory<ProductProvisioningRulesFactory> */
    use HasFactory;

    protected $table = 'product_provisioning_rules';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'auto_provision',
        'send_welcome_email',
        'welcome_email_template',
        'node_group_key',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_provision' => 'boolean',
            'send_welcome_email' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shouldAutoProvision(): bool
    {
        return $this->auto_provision;
    }

    public function shouldSendWelcomeEmail(): bool
    {
        return $this->send_welcome_email;
    }

    public function hasNodeGroup(): bool
    {
        return filled($this->node_group_key);
    }

    protected static function newFactory(): ProductProvisioningRulesFactory
    {
        return ProductProvisioningRulesFactory::new();
    }
}
