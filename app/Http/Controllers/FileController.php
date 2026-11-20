<?php

namespace App\Http\Controllers;

use App\Models\File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends Controller
{
    public function create(Request $request)
    {
        $path = '';
        $pathS3 = '';
        $item = null;

        try {
            $userSesion = $request->user();
            $idUserSesion = $userSesion->id;
            $name = (string) $request->name;
            $modelName = (string) $request->modelName;
            $modelId = $request->modelId;

            $type = $request->type;
            $extension = $request->extension;
            $storage = (string) $request->storage;
            $state = $request->status;
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $maintainFile = $request->boolean('maintain');

            if (!$name || !$storage || !$modelName || $modelId === null || $modelId === '') {
                throw new Exception('Faltan datos del archivo (name, storage, modelName, modelId).');
            }

            if (!$extension && $request->hasFile('upload')) {
                $extension = $request->file('upload')->getClientOriginalExtension();
            }
            $extension = ltrim(strtolower((string) $extension), '.');
            if ($extension === '') {
                $extension = 'jpg';
            }

            // Stable name: DB url always matches the file on disk.
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
                    try {
                        $this->deleteStoredFile($old, $storage);
                    } catch (Throwable $cleanupError) {
                        // Cleanup must not block a new upload.
                    }
                    $old->delete();
                }

                // Overwrite stable path and best-effort remove legacy timestamped copies.
                try {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                    foreach ($disk->files((string) $modelId) as $existing) {
                        $base = basename((string) $existing);
                        if ($base !== $nameComplete && str_starts_with($base, $name.'-')) {
                            $disk->delete($existing);
                        }
                    }
                } catch (Throwable $cleanupError) {
                    // Ignore listing/delete issues on some hosts; put() will overwrite.
                }
            }

            if ($request->hasFile('upload')) {
                $uploaded = $request->file('upload');
                if (!$uploaded->isValid()) {
                    throw new Exception('El archivo subido no es válido (posible límite del servidor).');
                }
                $written = $disk->put($path, fopen($uploaded->getRealPath(), 'r'));
            } else {
                $file = $request->input('file');
                if (!$file) {
                    throw new Exception('No se recibió el archivo.');
                }
                $written = $disk->put($path, base64_decode($file));
            }

            if ($written === false) {
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
        } catch (Throwable $e) {
            return response()->json([
                'message' => [
                    [
                        'text' => 'Se ha presentado un error',
                        'detail' => $e->getMessage(),
                    ],
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => [
                [
                    'text' => 'Imagen guardada',
                    'detail' => $pathS3 ?? $path,
                ],
            ],
            'data' => $item,
        ], Response::HTTP_OK);
    }

    public function get(Request $request)
    {
        try {
            $name = $request->name;
            $modelName = $request->modelName;
            $modelId = $request->modelId;

            $item = File::where('name', $name)
                ->where('model_id', $modelId)
                ->where('model_name', $modelName)
                ->first();
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $item,
            'message' => 'Succeed',
        ], JsonResponse::HTTP_OK);
    }

    public function update(Request $request, int $id)
    {
        try {
            $item = File::find($id)->update($request->all());
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $item,
            'message' => 'Succeed',
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
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $deleted,
            'message' => 'Succeed',
        ], JsonResponse::HTTP_OK);
    }

    /**
     * DB url is "{disk}/{modelId}/{filename}"; disk root is already .../public/{disk}.
     */
    protected function deleteStoredFile(File $item, ?string $preferredDisk = null): void
    {
        $relativePath = ltrim(str_replace('\\', '/', (string) $item->url), '/');
        if ($relativePath === '') {
            return;
        }

        $diskName = $preferredDisk ?: ($item->model_name ?: 'public');
        $prefix = $diskName.'/';
        if (str_starts_with($relativePath, $prefix)) {
            $relativePath = substr($relativePath, strlen($prefix));
        } else {
            // url like "products/5/file.jpg" while preferred disk unknown: use first segment as disk.
            $parts = explode('/', $relativePath, 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $diskName = $parts[0];
                $relativePath = $parts[1];
            }
        }

        $relativePath = ltrim($relativePath, '/');
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
        $imageUrl = $request->query('imageUrl');
        $route = $request->query('route');
        $date = $request->query('date');

        if (!$imageUrl) {
            return response()->json(['error' => 'URL de la imagen no proporcionada'], 400);
        }

        $imageContent = file_get_contents($imageUrl);

        if ($imageContent === false) {
            return response()->json(['error' => 'No se pudo obtener la imagen'], 404);
        }

        $fileName = basename($imageUrl);

        return response($imageContent, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Content-Disposition', 'attachment; filename="'.$route.' - '.$date.' - '.$fileName.'"');
    }
}
