<?php

namespace FacturaScripts\Plugins\Anticipos\Extension\Model\Base;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Lib\ReceiptGenerator;
use FacturaScripts\Core\Model\DocTransformation;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Core\Model\FacturaProveedor;
use FacturaScripts\Core\Model\ReciboProveedor;
use FacturaScripts\Plugins\Anticipos\Model\AnticipoP;

/**
 * Description of SalesDocument
 *
 * @property $idestado
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 * @method primaryColumnValue()
 * @method primaryColumn()
 * @method modelClassName()
 */
class PurchaseDocument
{
    public function saveUpdate(): Closure
    {
        return function () {
            $estado = new EstadoDocumento();
            $estado->loadFromCode($this->idestado);

            if (empty($estado->generadoc)) {
                return;
            }

            $whereAnticipos = [new DataBaseWhere($this->primaryColumn(), $this->primaryColumnValue())];
            $anticipos = (new AnticipoP())->all($whereAnticipos, [], 0, 0);

            if (count($anticipos) === 0) {
                return;
            }

            $primaryColumns = [
                'AlbaranProveedor' => 'idalbaran',
                'FacturaProveedor' => 'idfactura',
                'PedidoProveedor' => 'idpedido',
                'PresupuestoProveedor' => 'idpresupuesto',
            ];

            $whereTransformation = [
                new DataBaseWhere('model1', $this->modelClassName()),
                new DataBaseWhere('iddoc1', $this->primaryColumnValue())
            ];

            $transformation = new DocTransformation();
            $transformation->loadFromCode('', $whereTransformation);

            if (!$transformation->model2 && !$transformation->iddoc2) {
                return;
            }

            foreach ($anticipos as $anticipo) {
                $anticipo->{$primaryColumns[$transformation->model2]} = $transformation->iddoc2;

                if (false === $anticipo->save()) {
                    ToolBox::log('Anticipos')->warning("Error al actualizar el anticipo.");
                    return;
                }
            }

            //Si es una Factura generamos los recibos correspondientes.
            if ('FacturaCliente' === $transformation->model2 && $transformation->iddoc2) {
                $factura = new FacturaProveedor();

                if (false === $factura->loadFromCode($transformation->iddoc2)) {
                    return;
                }

                //Eliminamos el recibo generado automaticamente.
                foreach ($factura->getReceipts() as $recibo) {
                    $recibo->delete();
                }

                //Generamos los nuevos recibos en base a los anticipos.
                $numero = 1;
                foreach ($anticipos as $anticipo) {
                    $recibo = new ReciboProveedor();

                    $recibo->codproveedor = $anticipo->codproveedor;
                    $recibo->coddivisa = $anticipo->coddivisa;
                    $recibo->idempresa = $anticipo->idempresa;
                    $recibo->idfactura = $anticipo->idfactura;
                    $recibo->importe = $anticipo->importe;
                    $recibo->nick = $anticipo->user;
                    $recibo->numero = $numero++;
                    $recibo->fecha = $anticipo->fecha;
                    $recibo->setPaymentMethod($anticipo->codpago);
                    $recibo->save();
                }

                //Generamos el recibo por el saldo pendiente si ubiese y actualizamos la factura.
                $generator = new ReceiptGenerator();
                $generator->generate($factura);
                $generator->update($factura);
            }
        };
    }
}
