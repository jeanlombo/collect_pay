<?php

function canAccessMenu($menu)
{
    $role = $_SESSION['role'] ?? '';

    if ($role === 'SUPER_ADMIN') {
        return true;
    }

    $permissions = [

        'CONTRIBUABLES' => [
            'CONSTATATEUR',
            'CHEF_CENTRE'
        ],

        'CONSTATATION' => [
            'CONSTATATEUR',
            'CHEF_CENTRE'
        ],

        'LIQUIDATION' => [
            'LIQUIDATEUR',
            'CHEF_CENTRE'
        ],

        'CONTROLE' => [
            'CONTROLEUR',
            'CHEF_CENTRE'
        ],

        'ORDONNANCEMENT' => [
            'ORDONNATEUR',
            'CHEF_CENTRE'
        ],

        'PAIEMENT' => [
            'CAISSIER',
            'RECOUVREMENT',
            'CHEF_RECOUVREMENT'
        ],

        'RECOUVREMENT' => [
            'RECOUVREMENT',
            'CHEF_RECOUVREMENT'
        ],

        'PENALITES' => [
            'CHEF_RECOUVREMENT'
        ],

        'INSPECTION' => [
            'INSPECTEUR',
            'AUDITEUR'
        ],

        'PILOTAGE' => [
            'DG',
            'DIRECTION_FINANCIERE',
            'CHEF_CENTRE',
            'CHEF_RECOUVREMENT',
            'INSPECTEUR',
            'AUDITEUR'
        ],

        'ADMINISTRATION' => [
            'SUPER_ADMIN'
        ],

        'PARAMETRAGE' => [
            'SUPER_ADMIN'
        ]
    ];

    return in_array($role, $permissions[$menu] ?? []);
}