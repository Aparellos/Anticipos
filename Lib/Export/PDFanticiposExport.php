<?php

namespace FacturaScripts\Plugins\Anticipos\Lib\Export;

use FacturaScripts\Core\Tools;

class PDFanticiposExport extends \FacturaScripts\Core\Lib\Export\PDFExport
{
	public function addListModelPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
		return false;
	}

    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->newPage();
        $idempresa = $model->idempresa ?? null;
        $this->insertHeader($idempresa);

        $tableCols = [];
        $tableColsTitle = [];
        $tableOptions = [
            'width' => $this->tableWidth,
            'showHeadings' => 0,
            'shaded' => 0,
            'lineCol' => [1, 1, 1],
            'cols' => []
        ];

        // Get the columns
        $this->setTableColumns($columns, $tableCols, $tableColsTitle, $tableOptions);

        $tableDataAux = [];

		$DatosTrans=array(
			"customer"=>"codcliente",
			"method-payment"=>"codpago",
			"supplier"=>"codproveedor",
			"advance-linked-to"=>"fase",
			"date"=>"fecha",
			"delivery-note"=>"idalbaran",
			"invoice"=>"idfactura",
			"order"=>"idpedido",
			"estimation"=>"idpresupuesto",
			"project"=>"idproyecto",
			"amount"=>"importe",
			"note"=>"nota"
		);

		foreach ($tableCols as $key => $colName) {
			$value = $tableOptions['cols'][$key]['widget']->plainText($model);

			if (false !== strpos($colName, 'idempresa')) {
				continue;
			}elseif (false !== strpos($colName,'riesgomax')) {
				continue;
			}elseif (false !== strpos($colName, 'total')) {
				continue;
			}elseif (false !== strpos($colName, 'nick')) {
				continue;
			}

			$colName = Tools::lang()->trans(array_search($colName,$DatosTrans));
			$tableDataAux[] = ['key' => $colName, 'value' => $this->fixValue($value)];
		}

        $title .= ': ' . $model->primaryDescription();
        $this->pdf->ezText("\n" . $this->fixValue($title) . "\n", self::FONT_SIZE + 6);
        $this->newLine();

        $this->insertParallelTable($tableDataAux, '', $tableOptions);
        $this->insertFooter();
        return true;
    }
}