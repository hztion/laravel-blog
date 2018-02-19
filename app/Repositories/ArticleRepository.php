<?php


namespace App\Repositories;


use App\Models\Article;
use Illuminate\Support\Facades\DB;

class ArticleRepository
{
    public function selectAll()
    {
        return Article::all();
    }

    /**
     * 首页文章列表
     * @return mixed
     */
    public function indexArticles()
    {
        return Article::where('publish_status', '2')
            ->withCount('comments')
            ->with('tags')
            ->with('articleCategory')
            ->orderBy('created_at', 'desc')
            ->latest()
            ->paginate(8);
    }

    /**
     * 增加浏览量
     * @param Article $article
     */
    public function addPageViews(Article $article)
    {
        $article->page_views += 1;
        $article->save();
    }

    public function showArticle(Article $article)
    {
        //取得当前文章的总评论数
        $article = $article->withCount('comments')
            ->find($article->id)
            ->load('comments', 'tags', 'user');
        $article->comments = $article->comments()->withCount('likes')->get();
        $article->content_html = preg_replace("/<img\s+src=['\"](.+)['\"]\s(.*('|\"))>/", '<img src="' . asset('storage') . '${1}" ${2}>', $article->content_html);
        return $article;
    }

    public function archives()
    {
        if (request('year')&&request('month')){
            return Article::where('publish_status', '=', '2')
                ->select(['*'])
                ->orderBy('published_at', 'desc')
                ->filter(request()->only(['year', 'month']))
                ->latest()
                ->paginate(10);
        }else{
            return Article::where('publish_status', '=', '2')
                ->select(['*', DB::raw("DATE_FORMAT( published_at,'%Y-%m') as published_date")])
                ->orderBy('published_at', 'desc')
                ->latest()
                ->get()
                ->groupBy('published_date')
                ->paginate(10);
        }

    }

    public function search($keywords = '')
    {
        return Article::where('title', 'like', "%{$keywords}%")
            ->orWhere('summary', 'like', "%{$keywords}%")
            ->orWhere('content_html', 'like', "%{$keywords}%")
            ->paginate(10);
    }

    /**
     * 删除文章
     * @param Article $article
     * @throws \Exception
     */
    public function destroy(Article $article)
    {
        $article_tags = \DB::table('article_tag')->where('article_id', $article->id)->get(['tag_id']);
        if (count($article_tags) > 0) {
            foreach ($article_tags ?? [] as $article_tag) {
                $count = \DB::table('article_tag')->where('tag_id', $article_tag->tag_id)->count();
                if ($count <= 1) {
                    Tag::destroy($article_tag->tag_id);
                }
            }
        }
        $article->tags()->detach();
        $article->delete();
    }
}