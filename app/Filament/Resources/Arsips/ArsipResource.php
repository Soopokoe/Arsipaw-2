<?php

namespace App\Filament\Resources\Arsips;

use App\Filament\Resources\Arsips\Pages\ManageArsips;
use App\Models\Arsip;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Actions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Filters\SelectFilter;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Services\KtpOcrService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;




class ArsipResource extends Resource
{
    protected static ?string $model = Arsip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nomor_surat')->required(),

                TextInput::make('nama_arsip')
                    ->label('Nama Arsip')
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                TextInput::make('nama_pewaris')
                    ->label('Nama Almarhum/ah')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(
                        fn($state, Set $set) =>
                        $set('nama_arsip', "Surat Pernyataan Ahli Waris Almarhum/ah {$state}")
                    ),

                TextInput::make('nik_pewaris')
                    ->required()
                    ->reactive(),

                TextInput::make('rw')
                    ->required()
                    ->numeric()
                    ->reactive()
                    ->minValue(0),
                FileUpload::make('files')
                    ->multiple()
                    ->label('Files (Max. 8MB)')
                    ->disk('public')
                    ->directory('arsip')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                    ])
                    ->maxSize(8192) // 8MB
                    ->reorderable()
                    ->getUploadedFileNameForStorageUsing(
                        fn(TemporaryUploadedFile $file, Get $get): string =>
                        Str::of($get('nama_pewaris') ?? 'nama')
                            ->replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '')
                            ->slug('_')
                        . '_RW' . ($get('rw') ?? '-')
                        . '_' . now()->format('Y-m-d')
                        . '.' . $file->getClientOriginalExtension()
                    )
                    ->required()
                    ->reactive(), // ✅ PENTING juga

                DatePicker::make('tgl_upload')
                    ->required()
                    ->default(now()
                        ->timezone('Asia/Jakarta'))
                    ->live(),

                Actions::make([
                    Action::make('scan_ocr')
                        ->label('Scan OCR')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('primary')
                        ->action(function (Get $get, Set $set) {

                            $files = $get('files');

                            if (empty($files) || !is_array($files)) {
                                return;
                            }

                            $first = $files[0];

                            if ($first instanceof TemporaryUploadedFile) {
                                $path = $first->getRealPath()
                                    ?: storage_path('app/private/livewire-tmp/' . $first->getFilename());
                            } else {
                                $path = storage_path('app/public/' . $first);
                            }

                            if (!$path || !file_exists($path)) {
                                Log::warning('OCR FILE NOT FOUND', ['path' => $path]);
                                return;
                            }

                            Log::info('OCR PATH (BUTTON)', ['path' => $path]);

                            $ocr = app(KtpOcrService::class)->extract($path);

                            Log::info('OCR RESULT (BUTTON)', ['ocr' => $ocr]);

                            if (!empty($ocr['nama_pewaris'])) {
                                $set('nama_pewaris', $ocr['nama_pewaris']);

                                $set('nama_arsip', 'Surat Pernyataan Ahli Waris Almarhum/ah ' . $ocr['nama_pewaris']);
                            }

                            if (!empty($ocr['nik'])) {
                                $set('nik_pewaris', $ocr['nik']);
                            }

                            if (!empty($ocr['rw'])) {
                                $set('rw', $ocr['rw']);
                            }
                        }),
                ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_surat')
                    ->searchable(),
                TextColumn::make('nama_arsip')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->getStateUsing(fn($record) => "
                        Nama Almarhum/ah : {$record->nama_pewaris},<br>
                        NIK Almarhum/ah  : {$record->nik_pewaris},<br>
                        Warga RW         : {$record->rw},<br>
                        Kelurahan Pela Mampang
                    ")
                    ->html()
                    ->searchable(query: function ($query, string $search) {
                        $query->where('nama_pewaris', 'like', "%{$search}%")
                            ->orWhere('nik_pewaris', 'like', "%{$search}%");
                    }),

                TextColumn::make('rw')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tgl_upload')
                    ->date()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('files')
                    ->label('Files')
                    ->formatStateUsing(function (Arsip $record) {
                        $files = $record->files;

                        if (!is_array($files) || count($files) === 0) {
                            return '0 file';
                        }

                        return count($files) . ' file';
                    })
                    // ->url(function (Arsip $record) {
                    //     $files = $record->files;

                    //     if (!is_array($files) || count($files) === 0) {
                    //         return null;
                    //     }

                    //     return asset('storage/app/public/' . $files[0]);
                    // })

                    ->url(function (Arsip $record) {
                        $files = $record->files;

                        if (!is_array($files) || count($files) === 0) {
                            return null;
                        }

                        return Storage::url($files[0]);
                    })
                    ->openUrlInNewTab(),

            ])
            ->filters([
                SelectFilter::make('rw')
                    ->label('Filter RW')
                    ->options(
                        Arsip::query()
                            ->select('rw')
                            ->distinct()
                            ->orderBy('rw')
                            ->pluck('rw', 'rw')
                            ->toArray()
                    ),

                SelectFilter::make('tahun')
                    ->label('Filter Tahun')
                    ->options(
                        Arsip::query()
                            ->selectRaw('YEAR(tgl_upload) as tahun')
                            ->distinct()
                            ->orderByDesc('tahun')
                            ->pluck('tahun', 'tahun')
                            ->toArray()
                    )
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereYear('tgl_upload', $data['value']);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Arsip $record) {
                        if (empty($record->files) || !is_array($record->files)) {
                            abort(404, 'File tidak ditemukan.');
                        }

                        $filePath = $record->files[0];

                        if (!Storage::disk('public')->exists($filePath)) {
                            abort(404, 'File tidak ditemukan di storage.');
                        }

                        return response()->download(
                            Storage::disk('public')->path($filePath)
                        );
                    }),

                DeleteAction::make()
                    ->before(function (Arsip $record) {
                        if (is_array($record->files)) {
                            foreach ($record->files as $file) {
                                if (Storage::disk('public')->exists($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            }
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageArsips::route('/'),
        ];
    }
}