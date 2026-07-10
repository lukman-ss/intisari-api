<?php

declare(strict_types=1);

namespace App\Controllers;

use Lukman\Http\Request;
use Lukman\Http\Response;
use App\Support\ApiResponse;
use App\Support\RequestValidator;
use App\Support\Slugger;
use App\Support\AuthManager;
use App\Repositories\PostRepository;
use App\Resources\PostResource;

class PostsController extends Controller
{
    private PostRepository $repository;
    private AuthManager $authManager;
    private RequestValidator $validator;

    public function __construct()
    {
        $this->repository = app(PostRepository::class);
        $this->authManager = app(AuthManager::class);
        $this->validator = app(RequestValidator::class);
    }

    public function index(Request $request): Response
    {
        $viewerId = $this->authManager->id();
        if (!$viewerId) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $query = $request->query();
        $page = isset($query['page']) && is_scalar($query['page']) ? (int) $query['page'] : 1;
        $perPage = isset($query['per_page']) && is_scalar($query['per_page']) ? (int) $query['per_page'] : 15;
        
        $filters = [];
        if (isset($query['status'])) {
            $filters['status'] = (string) $query['status'];
        }
        if (isset($query['search'])) {
            $filters['search'] = (string) $query['search'];
        }
        if (isset($query['sort'])) {
            $filters['sort'] = (string) $query['sort'];
        }
        if (isset($query['direction'])) {
            $filters['direction'] = (string) $query['direction'];
        }

        $result = $this->repository->paginateForViewer($viewerId, $page, $perPage, $filters);

        return ApiResponse::paginated(PostResource::collection($result['items']), $result['meta'], 'Posts retrieved');
    }

    public function show(Request $request, string $id): Response
    {
        $post = $this->repository->findById((int) $id);

        if (!$post) {
            throw new \App\Exceptions\NotFoundException('Post not found');
        }

        if ($post['status'] === 'draft' && (int) $post['user_id'] !== $this->authManager->id()) {
            throw new \App\Exceptions\NotFoundException('Post not found');
        }

        return ApiResponse::success(['post' => PostResource::make($post)], 'Post retrieved');
    }

    public function store(Request $request): Response
    {
        $input = $this->input($request);

        if (!array_key_exists('status', $input)) {
            $input['status'] = 'draft';
        }

        $validated = $this->validator->validate($input, [
            'title' => 'required|max:150',
            'content' => 'required',
            'status' => 'required|in:draft,published',
        ]);

        $slug = Slugger::slug($validated['title']);

        // Quick uniqueness check for slug
        if ($this->repository->findBySlug($slug)) {
            $slug .= '-' . uniqid();
        }

        $post = $this->repository->create([
            'user_id' => $this->authManager->id(),
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'status' => $validated['status'],
        ]);

        return ApiResponse::created(['post' => PostResource::make($post)], 'Post created');
    }

    public function update(Request $request, string $id): Response
    {
        $post = $this->repository->findById((int) $id);

        if (!$post) {
            throw new \App\Exceptions\NotFoundException('Post not found');
        }

        if ((int) $post['user_id'] !== $this->authManager->id()) {
            if ($post['status'] === 'draft') {
                throw new \App\Exceptions\NotFoundException('Post not found');
            }
            throw new \App\Exceptions\ForbiddenException();
        }

        $input = $this->input($request);
        $rules = [];
        
        if ($request->method() === 'PUT') {
            if (!array_key_exists('status', $input)) {
                $input['status'] = 'draft';
            }
            $rules = [
                'title' => 'required|max:150',
                'content' => 'required',
                'status' => 'required|in:draft,published',
            ];
        } else {
            // PATCH
            if (isset($input['title'])) {
                $rules['title'] = 'max:150';
            }
            if (array_key_exists('status', $input)) {
                $rules['status'] = 'in:draft,published';
            }
        }

        if (!empty($rules)) {
            $this->validator->validate($input, $rules);
        }

        $data = [];
        if (isset($input['title'])) {
            $data['title'] = $input['title'];
            $data['slug'] = Slugger::slug($input['title']);
            
            // Uniqueness check, excluding self
            $existing = $this->repository->findBySlug($data['slug']);
            if ($existing && (int)$existing['id'] !== (int)$id) {
                $data['slug'] .= '-' . uniqid();
            }
        }
        
        if (isset($input['content'])) {
            $data['content'] = $input['content'];
        }
        
        if (isset($input['status'])) {
            $data['status'] = $input['status'];
        }

        $updated = $this->repository->update((int) $id, $data);

        return ApiResponse::success(['post' => PostResource::make($updated)], 'Post updated');
    }

    public function destroy(Request $request, string $id): Response
    {
        $post = $this->repository->findById((int) $id);

        if (!$post) {
            throw new \App\Exceptions\NotFoundException('Post not found');
        }

        if ((int) $post['user_id'] !== $this->authManager->id()) {
            if ($post['status'] === 'draft') {
                throw new \App\Exceptions\NotFoundException('Post not found');
            }
            throw new \App\Exceptions\ForbiddenException();
        }

        $this->repository->delete((int) $id);

        return ApiResponse::noContent();
    }
}
