<?php

namespace App\Enums;

enum Combustivel: string
{
    case Gasolina = 'gasolina';
    case Alcool = 'alcool';
    case Flex = 'flex';
    case Diesel = 'diesel';
    case Hibrido = 'hibrido';
    case Eletrico = 'eletrico';
}
