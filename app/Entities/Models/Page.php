<?php

namespace BookStack\Entities\Models;

use BookStack\Entities\Tools\PageContent;
use BookStack\Permissions\PermissionApplicator;
use BookStack\Uploads\Attachment;
use http\Client\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class Page.
 *
 * @property int          $chapter_id
 * @property string       $html
 * @property string       $markdown
 * @property string       $text
 * @property bool         $template
 * @property bool         $draft
 * @property int          $revision_count
 * @property string       $editor
 * @property Chapter      $chapter
 * @property Collection   $attachments
 * @property Collection   $revisions
 * @property PageRevision $currentRevision
 */
class Page extends BookChild
{
    use HasFactory;

    public static $listAttributes = ['name', 'id', 'slug', 'book_id', 'chapter_id', 'draft', 'template', 'text', 'created_at', 'updated_at', 'priority'];
    public static $contentAttributes = ['name', 'id', 'slug', 'book_id', 'chapter_id', 'draft', 'template', 'html', 'text', 'created_at', 'updated_at', 'priority'];

    protected $fillable = ['name', 'priority'];

    public string $textField = 'text';
    public string $htmlField = 'html';

    protected $hidden = ['html', 'markdown', 'text', 'pivot', 'deleted_at'];

    protected $casts = [
        'draft'    => 'boolean',
        'template' => 'boolean',
    ];

    /**
     * Get the entities that are visible to the current user.
     */
    public function scopeVisible(Builder $query): Builder
    {
        $query = app()->make(PermissionApplicator::class)->restrictDraftsOnPageQuery($query);

        return parent::scopeVisible($query);
    }

    /**
     * Get the chapter that this page is in, If applicable.
     *
     * @return BelongsTo
     */
    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Check if this page has a chapter.
     */
    public function hasChapter(): bool
    {
        return $this->chapter()->count() > 0;
    }

    /**
     * Get the associated page revisions, ordered by created date.
     * Only provides actual saved page revision instances, Not drafts.
     */
    public function revisions(): HasMany
    {
        return $this->allRevisions()
            ->where('type', '=', 'version')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get the current revision for the page if existing.
     */
    public function currentRevision(): HasOne
    {
        return $this->hasOne(PageRevision::class)
            ->where('type', '=', 'version')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get all revision instances assigned to this page.
     * Includes all types of revisions.
     */
    public function allRevisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    /**
     * Get the attachments assigned to this page.
     *
     * @return HasMany
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'uploaded_to')->orderBy('order', 'asc');
    }

    /**
     * Get the url of this page.
     */
    public function getUrl(string $path = ''): string
    {
        $parts = [
            'books',
            urlencode($this->book_slug ?? $this->book->slug),
            $this->draft ? 'draft' : 'page',
            $this->draft ? $this->id : urlencode($this->slug),
            trim($path, '/'),
        ];

        return url('/' . implode('/', $parts));
    }

    /**
     * Get this page for JSON display.
     */
    public function forJsonDisplay(): self
    {
        $refreshed = $this->refresh()->unsetRelations()->load(['tags', 'createdBy', 'updatedBy', 'ownedBy']);
        $refreshed->setHidden(array_diff($refreshed->getHidden(), ['html', 'markdown']));
        $refreshed->setAttribute('raw_html', $refreshed->html);
        $refreshed->html = (new PageContent($refreshed))->render();

        return $refreshed;
    }

    /**
     * Collect all pages linked on this page
     * @return Collection of local linked pages
     */
    public function getLocalLinkedPages() : Collection
    {
        $html = $this->html;
        $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
        $pages = new Collection();

        if(preg_match_all("/$regexp/siU", $html, $matches)) {
            foreach($matches[2] as $match)
            {
                $url = explode('/', $match);
                $lastPart = array_pop($url);
                $subPages = Page::visible()
                    ->where('slug', 'like', $lastPart)
                    ->get(['id', 'name', 'slug', 'book_id']);
                if($subPages->count() > 0)
                {
                    foreach($subPages as $subPage)
                    {
                        $pages = $pages->add($subPage)->unique();
                    }
                }
            }
        }
        return $pages;
    }

    static public function replaceLinksToRelativePDFLinks(Collection $pages, int $offset=0, Collection $replacedValues=null)
    {
        $tempPages = new Collection($pages);
        foreach($tempPages as $page)
        {
            if($page instanceof Chapter)
            {
                foreach($page->getVisiblePages() as $page)
                {
                    $tempPages->add($page);
                }
            }
        }
        foreach($tempPages->keys() as $key)
        {
            $page = $tempPages[$key];
            $html = $page->html;
            $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
            if(preg_match_all("/$regexp/siU", $html, $matches)) {
                foreach($matches[2] as $match)
                {
                    if(str_contains($match, "/pages/") or str_contains($match, "/page/")) {
                        $url = explode('/', $match);
                        $lastPart = array_pop($url);
                        foreach ($tempPages->keys() as $key) {
                            $subPage = $tempPages[$key];
                            if ($subPage->slug == $lastPart) {
                                $replaceString = "#page-" . $subPage->id;
                                if ($replacedValues) {
                                    $replacedValues[$match] = $replaceString;
                                }
                                $page->html = str_replace($match, $replaceString, $page->html);
                            }
                        }
                    }
                }
            }

        }
    }

    /**
     * Get a visible page by its book and page slugs.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getBySlugs(string $bookSlug, string $pageSlug): self
    {
        return static::visible()->whereSlugs($bookSlug, $pageSlug)->firstOrFail();
    }

}


