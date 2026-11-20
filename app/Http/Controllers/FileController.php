<?php

namespace App\Http\Controllers;

use App\Models\File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FileController extends Controller
{
    public function create(Request $request)
    {
        $url = "";

        try {
            $userSesion = $request->user();
            $idUserSesion = $userSesion->id;
            $name = $request->name;
            $modelName = $request->modelName;
            $modelId = $request->modelId;

            $type = $request->type;
            $extension = $request->extension;
            $storage = $request->storage;
            $state = $request->status;
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $maintainFile = $request->maintain;
            $item = null;

            if (!$extension && $request->hasFile('upload')) {
                $extension = $request->file('upload')->getClientOriginalExtension();
            }
            $extension = ltrim(strtolower((string) $extension), '.');
            // Stable name so DB url always matches the file on disk (no -timestamp / -id suffixes).
            $nameComplete = $name.'.'.$extension;
            $path = $modelId.'/'.$nameComplete;
            $pathS3 = $storage.'/'.$path;
            $disk = Storage::disk($storage);

            $disk->makeDirectory((string) $modelId);

            if (!$maintainFile) {
                $previous = File::query()
                    ->where('name', $name)
                    ->where('model_id', $modelId)
                    ->where('model_name', $modelName)
                    ->get();

                foreach ($previous as $old) {
                    $this->deleteStoredFile($old);
                    $old->delete();
                }

                // Clean legacy timestamped copies: PHOTO_PRODUCT-123.jpg, PHOTO_PRODUCT-123-5.jpg
                foreach ($disk->files((string) $modelId) as $existing) {
                    $base = basename($existing);
                    if ($base === $nameComplete || str_starts_with($base, $name.'-')) {
                        $disk->delete($existing);
                    }
                }
            }

            // Multipart (videos/archivos grandes) o base64 (flujo legacy de imágenes)
            if ($request->hasFile('upload')) {
                $uploaded = $request->file('upload');
                if (!$uploaded->isValid()) {
                    throw new Exception('El archivo subido no es válido (posible límite del servidor).');
                }
                $status = $disk->put($path, fopen($uploaded->getRealPath(), 'r'));
            } else {
                $file = $request->input('file');
                if (!$file) {
                    throw new Exception('No se recibió el archivo.');
                }
                $status = $disk->put($path, base64_decode($file));
            }

            if (!$status) {
                throw new Exception('No se pudo guardar el archivo en almacenamiento.');
            }

            $item = File::create([
                'name' => $name,
                'model_name' => $modelName,
                'model_id' => $modelId,
                'type' => $type,
                'extension' => $extension,
                'url' => $pathS3,
                'registered_by' => $idUserSesion,
                'registered_date' => date('Y-m-d H:i:s'),
                'status' => $state,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => [
                    [
                        'text' => 'Se ha presentado un error',
                        'detail' => $e->getMessage()
                    ]
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' =>  $path,
            'data' => $item,
        ], Response::HTTP_OK);

    }

    public function get(Request $request)
    {
        try {
            $userSesion = $request->user();
            $idUserSesion = $userSesion->id;
            $name = $request->name;
            $modelName = $request->modelName;
            $modelId = $request->modelId;

            $item = File::where('name', $name)
                ->where('model_id', $modelId)
                ->where('model_name', $modelName)
                ->first();

            /* if ($item) { solo para url temparales de S3
                $item->urlS3 = Storage::disk($modelName)->temporaryUrl($item->url, Carbon::now()->addHour());
            } */

        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message'=>$e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $item,
            'message' => 'Succeed'
        ], JsonResponse::HTTP_OK);
    }

    function update(Request $request, int $id) {
        try {
            $item = File::find($id)->update($request->all());
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message'=>$e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $item,
            'message' => 'Succeed'
        ], JsonResponse::HTTP_OK);
    }

    public function delete(Request $request, $id)
    {
        try {
            $item = File::findOrFail($id);
            $this->deleteStoredFile($item);
            $deleted = $item->delete();
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $deleted,
            'message' => 'Succeed'
        ], JsonResponse::HTTP_OK);
    }

    /**
     * DB url is "{disk}/{modelId}/{filename}"; disk root is already .../public/{disk}.
     */
    protected function deleteStoredFile(File $item): void
    {
        $diskName = $item->model_name ?: 'public';
        $relativePath = (string) $item->url;

        $prefix = $diskName.'/';
        if (str_starts_with($relativePath, $prefix)) {
            $relativePath = substr($relativePath, strlen($prefix));
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return;
        }

        $disk = Storage::disk($diskName);
        if ($disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }
    }

    public function downloadImageFromUrl(Request $request)
    {
        // Obtener el contenido de la imagen desde la URL
        $imageUrl = $request->query('imageUrl');
        $route = $request->query('route');
        $date = $request->query('date');

        // Validamos que la URL no esté vacía
        if (!$imageUrl) {
            return response()->json(['error' => 'URL de la imagen no proporcionada'], 400);
        }

        $imageContent = file_get_contents($imageUrl);

        // Verifica si se pudo obtener el contenido
        if ($imageContent === false) {
            return response()->json(['error' => 'No se pudo obtener la imagen'], 404);
        }

        // Extraer el nombre del archivo de la URL
        $fileName = basename($imageUrl);

        // Crear una respuesta de descarga con el contenido de la imagen
        return response($imageContent, 200)
            ->header('Content-Type', 'image/jpeg')  // Ajusta el tipo de contenido según el tipo de imagen
            ->header('Content-Disposition', 'attachment; filename="' . $route . ' - '. $date . ' - '. $fileName . '"');
    }
}
