<?php

namespace App\Classes;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Pagopar {

	public string $baseURL;
	public string $publicToken;
	public string $privateToken;
	public string $expirationDays;
	public string $shipmentOptionsObservation;

	public function __construct()
	{
		$this->baseURL = config("app.pagopar_base_url");
		$this->publicToken = config("app.pagopar_public_token");
		$this->privateToken = config("app.pagopar_private_token");
		$this->expirationDays = config("app.pagopar_expiration_days");
		$this->shipmentOptionsObservation = config('app.pagopar_shipment_options_observation');
	}

	public function call($method, $path, $body = null)
	{
		$URL = $this->baseURL . $path;

		if ($method == "GET") $response = Http::withToken($this->accessToken)->get($URL, $body);
		if ($method == "POST") $response = Http::withToken($this->accessToken)->post($URL, $body);

		return json_decode($response->getBody());
	}

	public static function instance()
	{
		return new Pagopar();
	}

	public function postCities()
	{
		$body = [
			"token"         => sha1("{$this->privateToken}CIUDADES"),
			"token_publico" => $this->publicToken,
		];

		return $this->call("POST", "/ciudades/1.1/traer", $body);
	}

	public function postCategories()
	{
		$body = [
			"token"         => sha1("{$this->privateToken}CATEGORIAS"),
			"token_publico" => $this->publicToken,
		];

		return $this->call("POST", "/categorias/1.1/traer", $body);
	}

	public function postPaymentMethods()
	{
		$body = [
			"token"         => sha1("{$this->privateToken}FORMA-PAGO"),
			"token_publico" => $this->publicToken,
		];

		return $this->call("POST", "/forma-pago/1.1/traer", $body);
	}

	public function postCreateOrder($payload)
	{
		$body = [
			"token"               => sha1($this->privateToken . $payload["id_pedido_comercio"] . $payload["monto_total"]),
			"comprador"           => [
				"ruc"                  => $payload["ruc"],
				"email"                => $payload["email"],
				"ciudad"               => ($payload["ciudad"]) ? $payload["ciudad"] : 1,
				"nombre"               => $payload["nombre"],
				"telefono"             => $payload["telefono"],
				"direccion"            => $payload["direccion"],
				"documento"            => $payload["documento"],
				"coordenadas"          => $payload["coordenadas"],
				"razon_social"         => $payload["razon_social"],
				"tipo_documento"       => strtoupper($payload["tipo_documento"]),
				"direccion_referencia" => $payload["direccion_referencia"],
			],
			"public_key"          => $this->publicToken,
			"monto_total"         => $payload["monto_total"],
			"tipo_pedido"         => $payload["tipo_pedido"],
			"compras_items"       => $payload["compras_items"],
			"fecha_maxima_pago"   => Carbon::now()->addDays($this->expirationDays)->format("Y-m-d h:m:s"),
			"id_pedido_comercio"  => $payload["id_pedido_comercio"],
			"descripcion_resumen" => $payload["descripcion_resumen"],
		];

		$order = $this->call("POST", "/comercios/1.1/iniciar-transaccion", $body);

		$hash = $order["resultado"][0]["data"];

		return "https://www.pagopar.com/pagos/{$hash}";
	}

	public function formatItems($items = [])
	{
		$items_formatted = [];

		if (empty($items["opciones_envio"])) {
			$opciones_envio = [
				"metodo_retiro" => [
					"costo"       => 0,
					"observacion" => $this->shipmentOptionsObservation,
				],
				"metodo_aex"    => false,
			];
		}

		foreach ($items as $item) {
			$items_formatted[] = [
				"ciudad"                         => ($item["ciudad"]) ? $item["ciudad"] : 1,
				"nombre"                         => $item["nombre"],
				"cantidad"                       => strval($item["cantidad"]),
				"categoria"                      => ($item["categoria"]) ? $item["categoria"] : "980",
				"public_key"                     => $this->publicToken,
				"url_imagen"                     => $item["url_imagen"],
				"descripcion"                    => $item["descripcion"],
				"id_producto"                    => $item["id"],
				"precio_total"                   => $item["precio_total"],
				"vendedor_telefono"              => $item["vendedor_telefono"],
				"vendedor_direccion"             => $item["vendedor_direccion"],
				"vendedor_direccion_referencia"  => $item["vendedor_direccion_referencia"],
				"vendedor_direccion_coordenadas" => $item["vendedor_direccion_coordenadas"],
				"peso"                           => ($item["peso"]) ? $item["peso"] : "0.00",
				"largo"                          => ($item["largo"]) ? $item["largo"] : "0.00",
				"ancho"                          => ($item["ancho"]) ? $item["ancho"] : "0.00",
				"alto"                           => ($item["alto"]) ? $item["alto"] : "0.00",
				"envio_seleccionado"             => ($item["envio_seleccionado"]) ? $item["envio_seleccionado"] : "retiro",
				"costo_envio"                    => ($item["costo_envio"]) ? $item["costo_envio"] : 0,
				"opciones_envio"                 => ($item["opciones_envio"]) ? $item["opciones_envio"] : $opciones_envio,
			];
		}

		return $items_formatted;
	}

}
