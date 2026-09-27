<?php

return [
    'revenue' => 'ROI and break-even use returned rentals and completed extensions. Active/approved value is shown separately as ongoing revenue.',
    'roi' => '(Revenue - maintenance - purchase price) ÷ purchase price × 100%. Figures are provisional while some asset purchase prices are missing.',
    'bep' => '(Revenue - maintenance) ÷ purchase price × 100%. Progress uses recorded investment.',
    'utilization' => 'Rented hours ÷ active asset operating hours. Invalid return intervals use due_at; active legacy rentals use due_at to avoid unbounded usage.',
    'recommendation' => 'Healthy assets, operational actions, monitoring and deferred decisions are separated. History blocks decisions if fallback intervals reach 10%, or at least three overdue active rentals make up 10% of a unit’s history.',
];
