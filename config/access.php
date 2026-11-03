<?php

declare(strict_types=1);

return [
    'roles' => ['admin' => 'ადმინისტრატორი', 'editor' => 'რედაქტორი', 'viewer' => 'დამთვალიერებელი'],
    'permissions' => [
        'overview.view' => ['label' => 'მიმოხილვის ნახვა', 'group' => 'მიმოხილვა'],
        'projects.create' => ['label' => 'პროექტის დამატება', 'group' => 'ბინები და პროექტები'],
        'clients.view' => ['label' => 'კლიენტების ნახვა', 'group' => 'კლიენტები'],
        'clients.create' => ['label' => 'კლიენტის დამატება', 'group' => 'კლიენტები'],
        'clients.update' => ['label' => 'კლიენტის რედაქტირება', 'group' => 'კლიენტები'],
        'apartments.view' => ['label' => 'ბინების ნახვა', 'group' => 'ბინები და პროექტები'],
        'apartments.create' => ['label' => 'ბინის დამატება', 'group' => 'ბინები და პროექტები'],
        'apartments.update' => ['label' => 'ბინის რედაქტირება', 'group' => 'ბინები და პროექტები'],
        'contracts.view' => ['label' => 'ხელშეკრულებების ნახვა', 'group' => 'ხელშეკრულებები'],
        'contracts.create' => ['label' => 'ხელშეკრულების შექმნა', 'group' => 'ხელშეკრულებები'],
        'payments.view' => ['label' => 'გადახდების ნახვა', 'group' => 'გადახდები და იმპორტი'],
        'imports.view' => ['label' => 'იმპორტების ნახვა', 'group' => 'გადახდები და იმპორტი'],
        'payments.import' => ['label' => 'გადახდების იმპორტი', 'group' => 'გადახდები და იმპორტი'],
        'payments.reallocate' => ['label' => 'გადახდების გადანაწილება', 'group' => 'გადახდები და იმპორტი'],
        'users.manage' => ['label' => 'მომხმარებლებისა და უფლებების მართვა', 'group' => 'ადმინისტრირება'],
    ],
];
