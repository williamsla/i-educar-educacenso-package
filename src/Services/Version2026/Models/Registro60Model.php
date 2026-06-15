<?php

namespace iEducar\Packages\Educacenso\Services\Version2026\Models;

use App\Models\Educacenso\Registro60;

class Registro60Model extends Registro60
{
    public function hydrateModel($arrayColumns): void
    {
        array_unshift($arrayColumns, null);
        unset($arrayColumns[0]);

        $this->inepEscola = $arrayColumns[2];
        $this->inepAluno = $arrayColumns[4];
        $this->inepTurma = $arrayColumns[6];
        $this->etapaAluno = $arrayColumns[8] ?: null;
        $this->cargaHorariaIntegralizada = $arrayColumns[9];
        $this->tipoAtendimentoDesenvolvimentoFuncoesGognitivas = $arrayColumns[10];
        $this->tipoAtendimentoDesenvolvimentoVidaAutonoma = $arrayColumns[11];
        $this->tipoAtendimentoEnriquecimentoCurricular = $arrayColumns[12];
        $this->tipoAtendimentoEnsinoInformaticaAcessivel = $arrayColumns[13];
        $this->tipoAtendimentoEnsinoLibras = $arrayColumns[14];
        $this->tipoAtendimentoEnsinoLinguaPortuguesa = $arrayColumns[15];
        $this->tipoAtendimentoEnsinoSoroban = $arrayColumns[16];
        $this->tipoAtendimentoEnsinoBraile = $arrayColumns[17];
        $this->tipoAtendimentoEnsinoOrientacaoMobilidade = $arrayColumns[18];
        $this->tipoAtendimentoEnsinoCaa = $arrayColumns[19];
        $this->tipoAtendimentoEnsinoRecursosOpticosNaoOpticos = $arrayColumns[20];
        $this->recebeEscolarizacaoOutroEspacao = $arrayColumns[21];
        $this->transportePublico = $arrayColumns[22] ?: null;
        $this->poderPublicoResponsavelTransporte = $arrayColumns[23] ?: null;
        $this->veiculoTransporteBicicleta = $arrayColumns[24];
        $this->veiculoTransporteMicroonibus = $arrayColumns[25];
        $this->veiculoTransporteOnibus = $arrayColumns[26];
        $this->veiculoTransporteTracaoAnimal = $arrayColumns[27];
        $this->veiculoTransporteVanKonbi = $arrayColumns[28];
        $this->veiculoTransporteOutro = $arrayColumns[29];
        $this->veiculoTransporteAquaviarioCapacidade5 = $arrayColumns[30];
        $this->veiculoTransporteAquaviarioCapacidade5a15 = $arrayColumns[31];
        $this->veiculoTransporteAquaviarioCapacidade15a35 = $arrayColumns[32];
        $this->veiculoTransporteAquaviarioCapacidadeAcima35 = (int) $arrayColumns[33];
    }
}
