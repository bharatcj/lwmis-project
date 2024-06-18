<?php
  namespace LWMIS\Common;

  use Razorpay\IFSC\IFSC;
  // use Razorpay\IFSC\Bank;

  use Razorpay\IFSC\Exception\ServerError;
  use Razorpay\IFSC\Exception\InvalidCode;

  use Http\Client\HttpClient;
  use Http\Message\RequestFactory;
  use Http\Discovery\HttpClientDiscovery;
  use Psr\Http\Message\ResponseInterface;
  use Http\Discovery\MessageFactoryDiscovery;

  /**
   * Refer to https://github.com/razorpay/ifsc
  */
  class RazorPayIFSC
  {
    const API_BASE = 'https://ifsc.razorpay.com';

    const GET = 'GET';

    protected $httpClient = null;
    protected $code;
    private RequestFactory|\Http\Message\MessageFactory $requestFactory;

    /**
     * Creates a IFSC Client instance
     * @param Http\Client\HttpClient $httpClient A valid HTTPClient
     */
    function __construct($httpClient = null, RequestFactory $requestFactory = null)
    {
      $this->httpClient = $httpClient ?? HttpClientDiscovery::find();
      $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();
    }

    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    public function getBankCode()
    {
        return isset($this->code)?substr($this->code, 0, 4):$this->throwInvalidCode('null');
    }

    public function getBankName()
    {
        return IFSC::getBankName($this->getBankCode());
    }

    public function lookupIFSC(string $ifsc) // : Entity
    {
        if (IFSC::validate($ifsc))
        {
            $url = $this->makeUrl("/$ifsc");
            $request  = $this->requestFactory->createRequest(
                self::GET,
                $url
            );

            $response = $this->httpClient->sendRequest($request);

            return $this->parseResponse($response, $ifsc);
        }
        else
        {
            $this->throwInvalidCode($ifsc);
        }
    }

    /**
     * Parses a response into a IFSC\Entity instance
     * @param  ResponseInterface $response Response from the API
     * @param  string            $ifsc
     * @throws Exception\ServerError
     * @throws Exception\InvalidCode
     * @return Entity
     */
    protected function parseResponse(ResponseInterface $response, string $ifsc)
    {
      switch ($response->getStatusCode())
      {
        case 200:
          $retObj = [];
          $data = json_decode($response->getBody(), true);
          $retObj['ifsc'] = $this->code = $data['IFSC'];
          $retObj['bank_code'] = $data['BANKCODE'];
          $retObj['bank'] = $data['BANK'];
          $retObj['branch'] = $data['BRANCH'];
          $retObj['address'] = $data['ADDRESS'];
          $retObj['centre'] = $data['CENTRE'];
          $retObj['city'] = $data['CITY'];
          $retObj['district'] = $data['DISTRICT'];
          $retObj['state'] = $data['STATE'];
          $retObj['contact'] = $data['CONTACT'];
          $retObj['imps'] = $data['IMPS'];
          $retObj['neft'] = $data['NEFT'];
          $retObj['rtgs'] = $data['RTGS'];
          $retObj['upi'] = $data['UPI'];
          // $retObj['mirc'] = $data['MIRC'];
          return $retObj; //new Entity($response);
          break;

        case 404:
          $this->throwInvalidCode($ifsc);
          break;

        default:
          throw new ServerError('IFSC API returned invalid response: ' .  $ifsc);
          break;
      }
    }

    /**
     * @throws Exception\InvalidCode
     * @param  string $ifsc IFSC Code
     */
    protected function throwInvalidCode(string $ifsc)
    {
      throw new InvalidCode('Invalid IFSC: ' . $ifsc);
    }

    protected function makeUrl(string $path): string
    {
      return self::API_BASE . $path;
    }

    private function log($response, $is_request = false)
    {
      $date = new \DateTime('now', new \DateTimeZone('Asia/Kolkata'));
      $date_str = $date->format('Y-m-d H:i:s A');

      $file_path = "../logs/".$date->format('Y')."/".$date->format('m')."/";
      if (!file_exists($file_path)) {
        mkdir($file_path, 0777, true);
      }
      $file_name = $file_path."rp-ifsc-".$date->format('Y-m-d').".log";

      $txt = '['.$date_str.'] ['.($is_request?'Request':'Response').'] ['.$response.']'.PHP_EOL;
      \file_put_contents($file_name, $txt, FILE_APPEND);
    }
  }
?>
