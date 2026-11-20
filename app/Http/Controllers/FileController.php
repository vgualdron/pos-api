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

            // Crear un nombre aleatorio para la imagen
            $time = strtotime("now");
            if (!$extension && $request->hasFile('upload')) {
                $extension = $request->file('upload')->getClientOriginalExtension();
            }
            $nameComplete = $name."-".$time.".".$extension;
            $pathS3 = "$storage/$modelId/$nameComplete";
            $path = "$modelId/$nameComplete";

            $disk = Storage::disk($storage);
            $disk->makeDirectory((string) $modelId);

            // Multipart (videos/archivos grandes) o base64 (flujo legacy de imágenes)
            if ($request->hasFile('upload')) {
                $uploaded = $request->file('upload');
                if (!$uploaded->isValid()) {
                    throw new Exception('El archivo subido no es válido (posible límite del servidor).');
                }
                $contents = file_get_contents($uploaded->getRealPath());
                if ($contents === false || $contents === '') {
                    throw new Exception('No se pudo leer el archivo subido.');
                }
            } else {
                $file = $request->input('file');
                if (!$file) {
                    throw new Exception('No se recibió el archivo.');
                }
                $contents = base64_decode($file, true);
                if ($contents === false || $contents === '') {
                    throw new Exception('El contenido de la imagen no es válido (base64).');
                }
            }

            // Local disks often return false from put() when chmod/visibility fails,
            // even if the bytes were written. Write + verify by existence.
            $this->writeStoredFile($storage, $path, $contents);

            // Replace previous file: delete physical + DB row (old code only deleted the DB row).
            if (!$maintainFile) {
                $previous = File::query()
                    ->where('name', $name)
                    ->where('model_id', $modelId)
                    ->where('model_name', $modelName)
                    ->get();

                foreach ($previous as $old) {
                    $this->deletePhysicalFromUrl($storage, $old->url);
                    $old->delete();
                }

                // Remove leftover orphans in the same folder (e.g. old seed webp with -id suffix).
                try {
                    foreach ($disk->files((string) $modelId) as $existing) {
                        if ($existing === $path) {
                            continue;
                        }
                        $base = basename((string) $existing);
                        if (
                            $base === $name
                            || str_starts_with($base, $name.'.')
                            || str_starts_with($base, $name.'-')
                        ) {
                            $disk->delete($existing);
                        }
                    }
                } catch (\Throwable $e) {
                    // Listing can fail on some hosts; new file + DB row are already the source of truth.
                }
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

            // Determina el disco desde model_name
            $disk = $item->model_name ?? 'public'; // fallback a 'public' si no existe

            // Obtiene la ruta relativa dentro del disco (quita /storage/app/public/)
            $relativePath = str_replace('/storage/app/public/' . $disk . '/', '', $item->url);
            echo $relativePath . '<br>';
            echo $disk . '<br>';
            echo Storage::disk($disk)->exists($relativePath);
            // Verifica y elimina el archivo físico
            if ($relativePath && Storage::disk($disk)->exists($relativePath)) {
                echo 'entroooooooo';
                Storage::disk($disk)->delete($relativePath);
            }

            // Elimina el registro en base de datos
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
     * Persist file bytes to a configured disk and confirm the path exists.
     * Prefer direct local write to avoid Flysystem visibility/chmod false failures.
     */
    protected function writeStoredFile(string $diskName, string $path, string $contents): void
    {
        $root = config("filesystems.disks.{$diskName}.root");

        if (is_string($root) && $root !== '') {
            $fullPath = rtrim($root, "\\/").DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $directory = dirname($fullPath);

            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new Exception("No se pudo crear el directorio de almacenamiento: {$directory}");
            }

            if (file_put_contents($fullPath, $contents) === false) {
                throw new Exception("No se pudo escribir el archivo: {$fullPath}");
            }

            return;
        }

        $disk = Storage::disk($diskName);
        $written = $disk->put($path, $contents);

        if ($written === false && !$disk->exists($path)) {
            throw new Exception('No se pudo guardar el archivo en almacenamiento.');
        }
    }

    /**
     * DB url is usually "{disk}/{modelId}/{filename}" while the disk root is already .../public/{disk}.
     */
    protected function deletePhysicalFromUrl(string $diskName, ?string $url): void
    {
        $relativePath = ltrim(str_replace('\\', '/', (string) $url), '/');
        if ($relativePath === '') {
            return;
        }

        $prefix = $diskName.'/';
        if (str_starts_with($relativePath, $prefix)) {
            $relativePath = substr($relativePath, strlen($prefix));
        }

        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return;
        }

        try {
            $disk = Storage::disk($diskName);
            if ($disk->exists($relativePath)) {
                $disk->delete($relativePath);
            }
        } catch (\Throwable $e) {
            // Ignore cleanup failures; the new upload remains valid.
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
