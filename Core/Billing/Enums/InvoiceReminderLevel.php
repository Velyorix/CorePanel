<?php

namespace Core\Billing\Enums;

enum InvoiceReminderLevel: string
{
    case BeforeDue7 = 'before_due_7';
    case BeforeDue3 = 'before_due_3';
    case Due = 'due';
    case Overdue = 'overdue';
    case FinalWarning = 'final_warning';

    public function label(): string
    {
        return match ($this) {
            self::BeforeDue7 => __('Upcoming invoice reminder'),
            self::BeforeDue3 => __('Invoice due soon'),
            self::Due => __('Invoice due today'),
            self::Overdue => __('Overdue invoice'),
            self::FinalWarning => __('Final payment warning'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
