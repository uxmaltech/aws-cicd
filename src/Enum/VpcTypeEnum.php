<?php

namespace Uxmal\Devtools\Enum;

enum VpcTypeEnum: string
{
    case pub2_priv2_nat2 = '2 Publicas + 2 Privadas + 2 NAT';
    case pub2_priv1_nat1 = '2 Publicas + 1 Privada + 1 NAT';
}
