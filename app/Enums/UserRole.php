<?php

namespace App\Enums;

/**
 * Os três papéis do WWork, conforme 02-arquitetura.md.
 *
 * Não é o RBAC da Samaúma: aqui não há perfil com tabela de permissões nem
 * predicado de capacidade. São dois papéis operacionais e um administrativo.
 */
enum UserRole: string
{
    /** O fundador. Só abre o Filament em /admin. Nunca opera uma agência. */
    case Founder = 'founder';

    /** O "Líder Exausto": dono do perfil, que paga, agenda e também executa. */
    case Owner = 'owner';

    /** O convidado: executa só o próprio dia e nunca cadastra outro profissional. */
    case Invited = 'invited';

    public function canAccessAdminPanel(): bool
    {
        return $this === self::Founder;
    }

    public function isAgencyOwner(): bool
    {
        return $this === self::Owner;
    }
}
