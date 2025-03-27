<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Exception\RequestException;

class HypervergeController extends Controller
{
    private function base_url() {
        return config('kwyc-check.credential.base_url');
    }
    private function default_filestore(){
        return config('kwyc-check.credential.defaultFileStorePath');
    }
    private function get_header($transactionId){

        $header = [
            'appId' => config('kwyc-check.credential.appId'),
            'appKey' => config('kwyc-check.credential.appKey'),
            'transactionId' => $transactionId
        ];
        return $header;
    }

    public function validate_live_url(Request $request){
        $headers = $this->get_header($request->referenceCode);
        $requestURL = $this->base_url()."/checkLiveness";
        // dd($request->imageURL);
        if (!file_exists($request->imageURL)) {
            return "File not found.";
        }
        // $filepath = $request->imagePath ? $request->imagePath :$this->default_filestore();
        // if (file_exists(storage_path($filepath.'/' . $request->referenceCode.".JPG"))) {
        //     $imageURL =  storage_path($filepath.'/'.$request->referenceCode.".JPG");
        // }
        try{
            $client = new Client();
            $response = $client->post($requestURL, [
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => fopen($request->imageURL, 'r')
                    ]
                    ],
                'headers' => $headers
            ]);
            $responseBody = $response->getBody()->getContents();
            $resJSON = json_decode($responseBody);
            return $resJSON->result->summary;
        }
     catch (\Exception $e) {
        $err = ['action' => 'Image is unprocessable',
        'details' =>  $e->getMessage()];
        return json_decode(json_encode($err)) ;
        }
    }
    public function validate_live_base64(Request $request){

        $headers = $this->get_header($request->referenceCode);
        $requestURL = $this->base_url()."/checkLiveness";
        $base64Img = substr($request->base64img, strpos($request->base64img, ',') + 1);
        $base64Img = base64_decode($request->base64img);
        Storage::put('public/image/' .  $request->referenceCode.".JPEG", $base64Img);

        $client = new Client();
        $response = $client->post($requestURL, [
            'multipart' => [
                [
                    'name'     => 'image',
                    'contents' => fopen(storage_path('app/public/image/'.  $request->referenceCode.".JPEG"), 'r')
                ]
                ],
            'headers' => $headers
        ]);
        $responseBody = $response->getBody()->getContents();
        Storage::delete('public/image/' .$request->referenceCode.".JPEG");
        return $responseBody;
    }
    public function validate_id(Request $request){
        $headers = $this->get_header($request->referenceCode);
        $requestURL = $this->base_url()."/readId";

        if($request->imageURL){
        if (file_exists(storage_path($request->imageURL))) {
            $imageURL = storage_path($request->imageURL);
        }
            else{ return "File not found."; }
        }
        elseif($request->base64Img){
            $base64Img = substr($request->base64Img, strpos($request->base64Img, ',') + 1);
            $base64Img = base64_decode($request->base64Img);
            Storage::put('public/image/' .  $request->referenceCode.".JPEG", $base64Img);
            if (file_exists(Storage::path('public/image/'.$request->referenceCode.".JPEG"))) {
                $imageURL = Storage::path('public/image/'.$request->referenceCode.".JPEG");
            }
            else{ return "File not found."; }
        }
        else{
            return "Invalid request format";
        }
        $client = new Client();

        $response = $client->post($requestURL, [
            'multipart' => [
                [
                    'name'     => 'image',
                    'contents' => fopen($imageURL, 'r')
                ],
                [
                    'name' => 'countryId',
                    'contents' => $request->countryId
                ],
                [
                    'name' => 'documentId',
                    'contents' => $request->documentId
                ]
                ],
            'headers' => $headers
        ]);
        $responseBody = $response->getBody()->getContents();
        if(file_exists(Storage::path('public/image/'.$request->referenceCode.".JPEG"))){
           Storage::delete('public/image/' .$request->referenceCode.".JPEG");
        }
        return $responseBody;
    }

    public function face_match(Request $request)
    {
        $headers = $this->get_header($request->image1Path);
        $requestURL = $this->base_url() . "/matchFace";

        // 💡 Use full paths via Storage::path()
        if (Storage::exists('public/image/' . $request->image1Path . '.JPEG')) {
            $image1URL = Storage::path('public/image/' . $request->image1Path . '.JPEG');
        } else {
            return response()->json(['error' => 'Live selfie not found.'], 404);
        }

        if (file_exists($request->image2URL)) {
            $image2URL = $request->image2URL; // already resolved path
        } else {
            return response()->json(['error' => 'Stored profile image not found.'], 404);
        }

        $body = [
            [
                'name' => 'image1',
                'contents' => fopen($image1URL, 'r'),
            ],
            [
                'name' => 'image2',
                'contents' => fopen($image2URL, 'r'),
            ],
        ];

        if ($request->type) {
            $body[] = [
                'name' => 'type',
                'contents' => $request->type,
            ];
        }

        $client = new \GuzzleHttp\Client();
        $response = $client->post($requestURL, [
            'multipart' => $body,
            'headers' => $headers,
        ]);

        $responseBody = $response->getBody()->getContents();
        return $responseBody;
    }

//    public function face_match(Request $request){
//        $headers = $this->get_header($request->image1Path);
//        $requestURL = $this->base_url()."/matchFace";
//
//        if (file_exists($request->image1URL)) {
//            $image1URL =  storage_path('image/'.$request->image1Path.".JPG");
//            }
//        else{
//            return "File not found.";
//            }
//        if (file_exists($request->image2URL)) {
//            $image2URL =  storage_path('image/'.$request->referenceCode.".JPG");
//            }
//        else{
//            return "File not found.";
//        }
//
//        $body =[
//            [
//            'name' => 'image1',
//            'contents' =>fopen($image1URL, 'r')
//            ],
//            [
//            'name' => 'image2',
//            'contents' =>fopen($image2URL, 'r')
//            ]
//            ];
//
//        if($request->type){
//        $body[] =
//        [
//            'name' => 'type',
//            'contents' => $request->type
//        ];
//         }
//
//        $client = new Client();
//        $response = $client->post($requestURL, [
//                'multipart' => $body,
//                'headers' => $headers
//            ]);
//
//            $responseBody = $response->getBody()->getContents();
//            return $responseBody;
//
//    }
    public function face_verify(Request $request){
        $headers = $this->get_header($request->referenceCode);
        $requestURL = $this->base_url()."/matchFace";

        if($request->imageURL){

            if (file_exists(storage_path($request->imageURL)))
            {
                $imageURL = storage_path($request->imageURL);
                $request->merge(['imageURL' => storage_path($request->imageURL)]);
            }
            else
            {
                return ["Message" =>"File not found."];
            }
            }
        elseif($request->base64Img)
            {
                $base64Img = substr($request->base64Img, strpos($request->base64Img, ',') + 1);
                $base64Img = base64_decode($request->base64Img);
                Storage::put('public/image/' .  $request->referenceCode.".JPEG", $base64Img);
                $request->merge(['imageURL' => Storage::path('public/image/'.$request->referenceCode.".JPEG")]);
                if (file_exists(Storage::path('public/image/'.$request->referenceCode.".JPEG"))) {
                    $imageURL = Storage::path('public/image/'.$request->referenceCode.".JPEG");
                    $request->merge(['imageURL' => $imageURL]);
                }
                else{
                return ["Message" =>"File not found."];
                 }
                }
            else{
                return ["Message" =>"Invalid request format"];
        }
        //check liveliness
        try{
            $live_response = $this->validate_live_url($request);
            if(!$live_response->action){
                if($live_response->action != 'pass'){
                    return ["Message" =>"Failed face check.",
                    "Summary" => $live_response->details];
                }
            else{
                return ["Message" =>"Failed face check.",
                "Summary" => $live_response];
            }
            }
        }
        catch(e)
        {
            return ["Message" =>"Error in live check."];
        }

        $client = new Client();
        $filepath = $request->imagePath ? $request->imagePath :$this->default_filestore();

        // if (file_exists($request->imageURL)) {
        //     $image1URL =  $request->imageURL;
        // }
        // else{
        //     return ["Message" =>"File not found."];
        // }

        if (file_exists(storage_path($filepath.'/' . $request->referenceCode.".JPG"))) {
            $image2URL =  storage_path($filepath.'/'.$request->referenceCode.".JPG");
        }
        else{
            Storage::delete('public/image/' .$request->referenceCode.".JPEG");//delete create image
            return ["Message" => "File not found."];
        }
        $body =[
            [
            'name' => 'image1',
            'contents' =>fopen($imageURL, 'r')
            ],
            [
            'name' => 'image2',
            'contents' =>fopen($image2URL, 'r')
            ]
            ];

        if($request->type){
        $body[] =
        [
            'name' => 'type',
            'contents' => $request->type
        ];
         }
        $client = new Client();
        $response = $client->post($requestURL, [
                'multipart' => $body,
                'headers' => $headers
            ]);

        $responseBody = $response->getBody()->getContents();
        if(file_exists(Storage::path('public/image/'.$request->referenceCode.".JPEG"))){
            Storage::delete('public/image/' .$request->referenceCode.".JPEG"); //delete create image
        }
        // Storage::delete('public/image/' .$request->referenceCode.".JPEG");
        return $responseBody;

    }

    public function save_temp_base64($base64img, $referenceCode){
        $base64Img = substr($base64img, strpos($base64img, ',') + 1);
        $base64Img = base64_decode($base64img);
        Storage::put('public/image/' .$referenceCode.".JPEG", $base64Img);
        return 'public/image/' .$referenceCode.".JPEG" ;
    }

}
