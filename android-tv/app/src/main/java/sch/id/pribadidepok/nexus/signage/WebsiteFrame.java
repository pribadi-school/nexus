package sch.id.pribadidepok.nexus.signage;

import android.content.Context;
import android.webkit.WebView;
import android.widget.FrameLayout;

/** Lay out a desktop page, then scale its complete viewport into the media area. */
final class WebsiteFrame extends FrameLayout {
    final WebView browser;
    private String pendingUrl;

    WebsiteFrame(Context context) {
        super(context);
        browser = new WebView(context);
        browser.setPivotX(0);
        browser.setPivotY(0);
        addView(browser, new LayoutParams(1, 1));
    }

    void loadPage(String url) {
        pendingUrl = url;
        requestLayout();
    }

    @Override protected void onMeasure(int widthSpec, int heightSpec) {
        super.onMeasure(widthSpec, heightSpec);
        int width = getMeasuredWidth(), height = getMeasuredHeight();
        if (width <= 0 || height <= 0) return;
        WebsiteLayout.Size layout = WebsiteLayout.fit(width, height, getResources().getDisplayMetrics().density);
        LayoutParams params = (LayoutParams) browser.getLayoutParams();
        params.width = layout.width;
        params.height = layout.height;
        browser.measure(MeasureSpec.makeMeasureSpec(layout.width, MeasureSpec.EXACTLY),
            MeasureSpec.makeMeasureSpec(layout.height, MeasureSpec.EXACTLY));
        browser.setScaleX(layout.scale);
        browser.setScaleY(layout.scale);
    }

    @Override protected void onLayout(boolean changed, int left, int top, int right, int bottom) {
        super.onLayout(changed, left, top, right, bottom);
        // Do not let a site's startup scripts see the temporary 1px viewport.
        LayoutParams layout = (LayoutParams) browser.getLayoutParams();
        if (pendingUrl != null && getWidth() > 0 && getHeight() > 0
                && browser.getWidth() == layout.width && browser.getHeight() == layout.height) {
            String url = pendingUrl;
            pendingUrl = null;
            browser.loadUrl(url);
        }
    }
}
