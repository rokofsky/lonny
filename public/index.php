<?php
declare(strict_types=1);
require __DIR__.'/../app/core.php';
$page=(string)($_GET['page']??'home');
$allowed=['home','about','workshops','watch','experiment','ask','playground','contact','privacy','admin','setup'];
if(!in_array($page,$allowed,true)){$page='not-found';http_response_code(404);}
function back(string $page='admin',array $extra=[]):never {redirectto($page,$extra);}
function posthidden(string $action):void {echo '<input type="hidden" name="csrf" value="'.h(token()).'"><input type="hidden" name="action" value="'.h($action).'">';}
function item_update(array &$items,string $id,callable $cb):void {foreach($items as &$item)if($item['id']===$id){$cb($item);break;}unset($item);}
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  csrf();
  $action=(string)($_POST['action']??'');
  if($action==='setup'){
    $existing=read_db();if($existing['admin'])throw new RuntimeException('Administrator is already configured.');
    if(!hash_equals($private['setup_key'],(string)($_POST['setup_key']??'')))throw new RuntimeException('Invalid setup key.');
    $email=filter_var(input('email',255),FILTER_VALIDATE_EMAIL);
    $pass=(string)($_POST['password']??'');
    if(!$email||strlen($pass)<12)throw new RuntimeException('Use a valid email address and a password of at least 12 characters.');
    mutate(function (&$db)use($email,$pass){if($db['admin'])throw new RuntimeException('Already configured.');$db['admin']=['email'=>strtolower($email),'hash'=>password_hash($pass,PASSWORD_DEFAULT)];});
    session_regenerate_id(true);$_SESSION['admin']=true;flash('Admin account created. You can now manage the website.');back('admin');
  }
  if($action==='login') {
    $db=read_db();$account=$db['admin']??null;$email=strtolower(input('email',255));$pass=(string)($_POST['password']??'');
    if(!$account||!hash_equals($account['email'],$email)||!password_verify($pass,$account['hash']))throw new RuntimeException('Invalid email or password.');
    session_regenerate_id(true);$_SESSION['admin']=true;flash('Welcome back.');back('admin');
  }
  if($action==='logout'){$_SESSION=[];session_regenerate_id(true);flash('Signed out.');back('home');}
  if(in_array($action,['inquire','ask','respond','register'],true)) {
    required_consent();
    if($action==='inquire'){
      $name=input('name',120);$email=input('email',255);$message=input('message',3000);
      if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||u_len($message)<10)throw new RuntimeException('Please enter your name, a valid email, and a message of at least 10 characters.');
      public_submit('inquiries',['name'=>$name,'email'=>$email,'organization'=>input('organization',150),'audience'=>input('audience',60),'message'=>$message,'status'=>'new']);
      flash('Thank you! Your enquiry was saved for Lonny to review.');back('contact');
    }
    if($action==='ask'){
      $name=input('name',100);$email=input('email',255);$q=input('question',1600);
      if(!filter_var($email,FILTER_VALIDATE_EMAIL)||u_len($q)<8)throw new RuntimeException('Please provide a valid email and a question of at least 8 characters.');
      public_submit('questions',['name'=>$name,'email'=>$email,'question'=>$q,'show_name'=>!empty($_POST['show_name']),'answer'=>'','status'=>'pending']);
      flash('Your question was saved for Lonny. It will not appear publicly unless selected and answered.');back('ask');
    }
    if($action==='respond'){
      $name=input('display_name',40);$body=input('body',400);
      if(u_len($body)<3)throw new RuntimeException('Please write a few words before submitting.');
      public_submit('responses',['name'=>$name,'body'=>$body,'status'=>'pending']);
      flash('Your invented word was submitted for moderation.');back('playground');
    }
    if($action==='register'){
      $db=read_db();$id=input('session_id',40);$session=null;
      foreach($db['sessions'] as $x)if($x['id']===$id && !empty($x['active']) && !empty($x['registration_open']) && strtotime($x['starts_at'])>time())$session=$x;
      if(!$session)throw new RuntimeException('This session is not open for registration.');
      $name=input('name',120);$email=input('email',255);
      if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Name and valid email are required.');
      $registrations=array_filter($db['registrations'],fn($x)=>$x['session_id']===$id && ($x['status']??'new')!=='cancelled');
      if(!empty($session['capacity'])&&count($registrations)>=(int)$session['capacity'])throw new RuntimeException('This session is full. Please enquire about another date.');
      public_submit('registrations',['session_id'=>$id,'name'=>$name,'email'=>$email,'notes'=>input('notes',800),'status'=>'new']);
      flash('Registration request received. This is not an automatically confirmed booking; Lonny will follow up.');back('workshops');
    }
  }
  if(!is_admin())throw new RuntimeException('Admin sign-in required.');
  $tab=input('tab',40)?:'overview';
  if($action==='setting'){
    $key=input('key',100);$allowedKeys=array_keys(defaults()['settings']);
    if(!in_array($key,$allowedKeys,true))throw new RuntimeException('Invalid setting.');
    mutate(function(&$db)use($key){$db['settings'][$key]=input('value',3000);});flash('Website copy updated.');back('admin',['tab'=>'copy']);
  }
  if($action==='save_workshop'){
    $id=input('id',40)?:new_id();$track=input('track',50);if(!in_array($track,['Teachers','Organizations','Learners'],true))throw new RuntimeException('Choose a valid audience.');
    $item=['id'=>$id,'track'=>$track,'title'=>input('title',150),'description'=>input('description',900),'format'=>input('format',100),'duration'=>input('duration',100),'active'=>!empty($_POST['active'])];
    if(!$item['title'])throw new RuntimeException('Workshop title is required.');
    mutate(function(&$db)use($item,$id){$found=false;foreach($db['workshops'] as &$w)if($w['id']===$id){$w=$item;$found=true;break;}unset($w);if(!$found)$db['workshops'][]=$item;});
    flash('Workshop saved.');back('admin',['tab'=>'workshops']);
  }
  if($action==='save_session'){
    $id=input('id',40)?:new_id();$workshop_id=input('workshop_id',40);$dt=input('starts_at',40);
    $db=read_db();if(!in_array($workshop_id,array_column($db['workshops'],'id'),true)||!strtotime($dt))throw new RuntimeException('Choose a workshop and valid date/time.');
    $item=['id'=>$id,'workshop_id'=>$workshop_id,'starts_at'=>date('c',strtotime($dt)),'location'=>input('location',180),'capacity'=>max(0,(int)($_POST['capacity']??0)),'active'=>!empty($_POST['active']),'registration_open'=>!empty($_POST['registration_open'])];
    mutate(function(&$db)use($item,$id){$found=false;foreach($db['sessions'] as &$s)if($s['id']===$id){$s=$item;$found=true;break;}unset($s);if(!$found)$db['sessions'][]=$item;});flash('Session saved.');back('admin',['tab'=>'sessions']);
  }
  if($action==='save_video'){
    $id=input('id',40)?:new_id();$url=input('url',300);
    if($url!==''&&!safe_video($url))throw new RuntimeException('Use a valid YouTube or Vimeo video URL.');
    $item=['id'=>$id,'title'=>input('title',160),'description'=>input('description',600),'url'=>$url,'active'=>!empty($_POST['active'])];
    if(!$item['title'])throw new RuntimeException('Video title required.');
    mutate(function(&$db)use($item,$id){$found=false;foreach($db['videos'] as &$v)if($v['id']===$id){$v=$item;$found=true;break;}unset($v);if(!$found)$db['videos'][]=$item;});flash('Video saved.');back('admin',['tab'=>'videos']);
  }
  if($action==='photo_upload'){
    if(empty($_FILES['photo']) || $_FILES['photo']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Choose an image to upload.');
    $file=$_FILES['photo'];if($file['size']>5*1024*1024)throw new RuntimeException('Maximum photo size is 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext||!getimagesize($file['tmp_name']))throw new RuntimeException('Only real JPG, PNG or WEBP images are accepted.');
    $name=new_id().'.'.$ext;$dest=__DIR__.'/media/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$dest))throw new RuntimeException('Media directory is not writable.');
    mutate(function(&$db)use($name){$db['photos'][]=['id'=>new_id(),'file'=>$name,'title'=>input('title',150),'caption'=>input('caption',300),'active'=>true];});flash('Photo uploaded.');back('admin',['tab'=>'photos']);
  }
  if($action==='moderate'){
    $target=input('target',40);$id=input('id',40);$status=input('status',40);
    if(!in_array($target,['questions','responses','inquiries','registrations'],true))throw new RuntimeException('Invalid collection.');
    $states=['questions'=>['pending','published','rejected'],'responses'=>['pending','approved','rejected'],'inquiries'=>['new','contacted','closed'],'registrations'=>['new','confirmed','cancelled']];
    if(!in_array($status,$states[$target],true))throw new RuntimeException('Invalid status.');
    mutate(function(&$db)use($target,$id,$status){item_update($db[$target],$id,function(&$item)use($target,$status){$item['status']=$status;if($target==='questions'){$item['answer']=input('answer',2000);$item['show_name']=!empty($_POST['show_name']);if($status==='published'&&!$item['answer'])throw new RuntimeException('Write an answer before publishing.');}});});
    flash('Entry updated.');back('admin',['tab'=>['questions'=>'questions','responses'=>'playground','inquiries'=>'inbox','registrations'=>'registrations'][$target]]);
  }
  if($action==='delete'){
    $target=input('target',40);$id=input('id',40);
    if(!in_array($target,['workshops','sessions','videos','photos','questions','responses','inquiries','registrations'],true))throw new RuntimeException('Invalid collection.');
    mutate(function(&$db)use($target,$id){foreach($db[$target] as $k=>$item)if($item['id']===$id){if($target==='photos'&&isset($item['file'])){$file=__DIR__.'/media/'.basename($item['file']);if(is_file($file))unlink($file);}unset($db[$target][$k]);} $db[$target]=array_values($db[$target]);});
    flash('Entry deleted.');back('admin',['tab'=>$target==='responses'?'playground':($target==='inquiries'?'inbox':$target)]);
  }
  if($action==='password'){
    $db=read_db();$cur=(string)($_POST['current_password']??'');$pass=(string)($_POST['new_password']??'');
    if(!password_verify($cur,$db['admin']['hash'])||strlen($pass)<12)throw new RuntimeException('Check current password; new password needs at least 12 characters.');
    mutate(function(&$db)use($pass){$db['admin']['hash']=password_hash($pass,PASSWORD_DEFAULT);});flash('Admin password changed.');back('admin',['tab'=>'security']);
  }
  throw new RuntimeException('Unrecognized action.');
 }catch(Throwable $e){$_SESSION['error']=$e->getMessage();redirectto($page,['tab'=>(string)($_POST['tab']??'overview')]);}
}
$db=read_db();$settings=$db['settings'];$flash=take_flash();$error=(string)($_SESSION['error']??'');unset($_SESSION['error']);
$nav=['home'=>'Home','about'=>'Meet Lonny','workshops'=>'Workshops','watch'=>'Watch & Learn','experiment'=>'Monthly Experiment','ask'=>'Ask Lonny','playground'=>'Playground'];
$meta=['home'=>'Language learning without the usual limits','about'=>'Meet Lonny and his philosophy','workshops'=>'Workshops and seminars','watch'=>'Watch & Learn','experiment'=>'Monthly Experiment','ask'=>'Ask Lonny','playground'=>'The Playground','contact'=>'Get in touch','admin'=>'Website administration','setup'=>'First-time setup','privacy'=>'Privacy notice'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#F7F9F8"><meta name="description" content="Workshops, teacher training and unforgettable language experiences with Lonny, a Suggestopedia practitioner since 1978."><title><?=h(($meta[$page]??'Page').' | '.$settings['brand'])?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600;6..72,700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/editorial.css?v=3"></head><body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header"><div class="header-inner"><a class="brand" href="<?=linkto('home')?>" aria-label="Lonny, homepage"><span class="brand-mark">LG</span><span class="brand-text">Lonny Gold<span class="brand-small">LANGUAGE · LEARNING · COMMUNICATION</span></span></a><button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Open menu">☰</button><nav id="main-nav" aria-label="Main navigation"><?php foreach($nav as $k=>$label):?><a <?=($page===$k?'aria-current="page"':'')?> href="<?=linkto($k)?>"><?=h($label)?></a><?php endforeach;?><a class="nav-cta" href="<?=linkto('contact')?>">Enquire about a workshop <span aria-hidden="true">↗</span></a></nav></div></header>
<?php if($flash):?><div class="notice success" role="status"><?=h($flash)?></div><?php endif; ?><?php if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif; ?>
<main id="main">
<?php
function eyebrow(string $label):void {echo '<p class="eyebrow">'.h($label).'</p>';}
function pagehero(string $eyebrow,string $title,string $desc=''):void {echo '<div class="pagehero container">';eyebrow($eyebrow);echo '<h1>'.h($title).'</h1>';if($desc)echo '<p class="lede">'.h($desc).'</p>';echo '</div>';}
function workshop_cards(array $db,?string $filter=null):void {
 foreach($db['workshops'] as $w)if(!empty($w['active'])&&(!$filter||$w['track']===$filter)){
 echo '<article class="workshop-card"><span class="small-tag">'.h($w['track']).' / '.h($w['format']).'</span><h3>'.h($w['title']).'</h3><p>'.h($w['description']).'</p><div class="card-bottom"><span>'.h($w['duration']).'</span><a href="'.linkto('contact',['format'=>$w['title']]).'">Enquire about this ↗</a></div></article>';
 }
}
function consent_ui():void {echo '<label class="consent"><input type="checkbox" name="consent" value="1" required><span>I agree that Lonny can store and use my submission to respond or review it, as described in the <a href="'.linkto('privacy').'">privacy notice</a>.</span></label>';}
function trap_ui():void {echo '<div class="honey" aria-hidden="true"><label>Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label></div>';}
function empty_state(string $title,string $desc):void {echo '<div class="empty-state"><span class="scribble">✳</span><h3>'.h($title).'</h3><p>'.h($desc).'</p></div>';}
if($page==='home'):
$photo=reset($db['photos']);
?>
<section class="hero"><div class="container hero-grid"><div class="hero-copy"><?php eyebrow('LANGUAGE · LEARNING · HUMAN CONNECTION');?><h1><?=h($settings['hero_title'])?></h1><p class="lede"><?=h($settings['hero_subtitle'])?></p><div class="actions"><a class="btn btn-blue" href="<?=linkto('workshops')?>">Explore workshops <span aria-hidden="true">↗</span></a><a class="btn btn-outline" href="<?=linkto('watch')?>">See Lonny at work <span aria-hidden="true">↗</span></a></div><div class="hero-proof"><span><strong>Since 1978</strong><small>Suggestopedia</small></span><span><strong>International</strong><small>Training and seminars</small></span><span><strong>For all ages</strong><small>Learning experiences</small></span></div></div><div class="hero-art"><figure class="hero-figure"><div class="hero-media"><div class="photo-background" aria-hidden="true"><span class="photo-kicker">A DIFFERENT WAY OF LEARNING</span><span class="photo-ornament">L<span>·</span>G</span><strong>Ideas come alive in the room.</strong><span class="photo-instruction">Workshop photography will appear here.</span></div><?php if($photo && !empty($photo['active'])):?><img class="hero-photo" src="media/<?=h($photo['file'])?>" alt="<?=h($photo['title'])?>" loading="eager" onload="this.classList.add('loaded')" onerror="this.remove()" ><?php else:?><img class="hero-photo" src="https://www.lonnygold.training/wp-content/uploads/2015/06/Le-formateur.jpg" alt="Lonny Gold — portrait from his existing website" loading="eager" onload="this.classList.add('loaded')" onerror="this.remove()" ><?php endif;?></div><figcaption><span>Lonny Gold</span><small><?php if($photo):?>From the workshop photo collection<?php else:?>Portrait source: <a href="https://www.lonnygold.training/lonny-gold-2/" target="_blank" rel="noopener noreferrer">Lonny's existing site ↗</a><?php endif;?></small></figcaption></figure></div></div></section>
<section class="section container"><div class="section-head"><div><?php eyebrow('THREE WAYS IN');?><h2>Where would you like to begin?</h2></div><p>A thoughtful approach to learning for educators, participants and organizations.</p></div><div class="door-grid"><a class="door door-gold" href="<?=linkto('workshops',['audience'=>'Teachers'])?>"><span class="door-icon">01</span><p>01 / FOR TEACHERS</p><h3>Bring new perspectives into your classroom.</h3><span>Explore teacher training ↗</span></a><a class="door door-blue" href="<?=linkto('workshops',['audience'=>'Organizations'])?>"><span class="door-icon">02</span><p>02 / FOR ORGANIZATIONS</p><h3>Build stronger ways to communicate.</h3><span>Bring Lonny in ↗</span></a><a class="door door-green" href="<?=linkto('workshops',['audience'=>'Learners'])?>"><span class="door-icon">03</span><p>03 / FOR LEARNERS</p><h3>Discover a different kind of learning.</h3><span>Find your course ↗</span></a></div></section>
<section class="manifesto"><div class="container manifesto-grid"><div class="philosophy-image"><div class="editorial-pullquote"><span class="quote-mark" aria-hidden="true">“</span><strong>How we learn matters as much as what we learn.</strong><span class="quote-caption">A philosophy of curiosity &amp; connection</span></div></div><div><?php eyebrow('THE APPROACH');?><h2>Learning starts with people.</h2><p><?=h($settings['manifesto'])?></p><a class="text-link" href="<?=linkto('about')?>">Meet Lonny Gold <span>↗</span></a></div></div></section>
<section class="section container"><div class="section-head"><div><?php eyebrow('TRY IT YOURSELF');?><h2>An invitation to explore.</h2></div><p>A tiny experiment to make your brain do something unexpected.</p></div><div class="feature-experiment"><div class="shape-doodle"><svg viewBox="0 0 300 220" aria-hidden="true"><path d="M65 55 L103 85 137 45 145 97 204 83 177 128 219 165 153 155 129 202 107 153 46 171 72 117 37 98Z" fill="#F7F9F8" stroke="#23383D" stroke-width="5"/><path d="M215 28C258 23 281 62 255 100S195 154 175 120 157 41 215 28Z" fill="#E3B85B" stroke="#23383D" stroke-width="5"/></svg></div><div><?php eyebrow('THE MONTHLY EXPERIMENT');?><h3><?=h($settings['experiment_title'])?></h3><p>Two mysterious sounds. Two shapes. A surprising connection. Takes about 90 seconds.</p><a class="btn btn-blue" href="<?=linkto('experiment')?>">Let's play <span>↗</span></a></div></div></section>
<section class="section videos-teaser"><div class="container"><div class="section-head"><div><?php eyebrow('FROM THE CLASSROOM');?><h2>See learning in action.</h2></div><a class="text-link" href="<?=linkto('watch')?>">Watch & Learn ↗</a></div><div class="video-teaser-grid"><div class="media-placeholder"><span class="play-symbol">▶</span><p>Teaching demonstrations</p><small>Video recordings can be added in the admin area</small></div><div class="video-side"><span>✳</span><h3>Discover the atmosphere of a learning experience.</h3><p>Teaching demonstrations, extraordinary activities, and real moments from Lonny’s seminars.</p><a href="<?=linkto('watch')?>" class="text-link">Open the video library ↗</a></div></div></div></section>
<section class="section container"><div class="question-banner"><div><?php eyebrow('A CONVERSATION');?><h2>Have a question for Lonny?</h2><p>Ask about learning, language, creativity, communication, or why schools are the way they are.</p><a class="btn btn-dark" href="<?=linkto('ask')?>">Ask a question <span>↗</span></a></div><div class="question-mark" aria-hidden="true">?</div></div></section>
<?php
elseif($page==='about'):
pagehero('MEET LONNY GOLD','The educator behind the experience.','A lifetime exploring how we learn, communicate and grow.');?>
<section class="section container about-grid"><div><div class="about-art"><div class="about-photo-fallback" aria-hidden="true"><span>Meet the educator behind the experience.</span><small>Personal photographs can be managed from the administration page.</small></div><?php if(!empty($db['photos'][0]['file'])):?><img class="about-photo" src="media/<?=h($db['photos'][0]['file'])?>" alt="<?=h($db['photos'][0]['title'])?>" loading="lazy" onload="this.classList.add('loaded')" onerror="this.remove()" ><?php else:?><img class="about-photo" src="https://www.lonnygold.training/wp-content/uploads/2015/06/Le-formateur.jpg" alt="Portrait of Lonny Gold from lonnygold.training" loading="lazy" onload="this.classList.add('loaded')" onerror="this.remove()" ><?php endif;?></div></div><div class="bio-copy"><h2><?=h($settings['bio_headline'])?></h2><p><?=nl2br(h($settings['bio_text']))?></p><div class="fact-chips"><span>Originally Canadian</span><span>Suggestopedia since 1978</span><span>International teacher trainer</span></div></div></section>
<section class="manifesto"><div class="container philosophy"><div><?php eyebrow('THE THINKING BEHIND IT');?><h2>What is Suggestopedia?</h2></div><div><p>Suggestopedia is an approach to teaching and learning associated with Georgi Lozanov. Its practice pays attention to the learning environment, relationships, emotion, music, imagination and the ways people respond beyond conscious effort.</p><p>Lonny’s work uses these ideas as a foundation for vivid language and teacher-training experiences. The easiest way to understand his approach may be to try an activity for yourself.</p><div class="actions"><a class="btn btn-blue" href="<?=linkto('experiment')?>">Try the experiment ↗</a><a class="btn btn-outline" href="https://www.hltmag.co.uk/dec20/suggestopedia" target="_blank" rel="noopener noreferrer">Read Lonny’s published article ↗</a></div></div></div></section>
<?php
elseif($page==='workshops'):
pagehero('WORKSHOPS & TRAINING','Experiences designed to make learning last.','Teacher development, language learning and communication workshops, all Suggestopedic in spirit.');
$aud=(string)($_GET['audience']??'');$tracks=['Teachers','Organizations','Learners'];if(!in_array($aud,$tracks,true))$aud='';
?><section class="section container"><div class="filter-tabs" role="navigation" aria-label="Filter workshops"><a class="<?=!$aud?'active':''?>" href="<?=linkto('workshops')?>">All</a><?php foreach($tracks as $track):?><a class="<?=$aud===$track?'active':''?>" href="<?=linkto('workshops',['audience'=>$track])?>"><?=h($track)?></a><?php endforeach;?></div><div class="workshop-grid"><?php workshop_cards($db,$aud?:null);?></div></section>
<section class="section container"><div class="section-head"><div><?php eyebrow('ON THE CALENDAR');?><h2>Upcoming sessions</h2></div><p>No invented dates. When Lonny schedules a real event, it appears here.</p></div><div class="session-list"><?php $sessionCount=0;foreach($db['sessions'] as $sess):if(empty($sess['active'])||strtotime($sess['starts_at'])<=time())continue;$sessionCount++;$work=null;foreach($db['workshops'] as $w)if($w['id']===$sess['workshop_id'])$work=$w;?><article class="session-row"><div><strong><?=h($work['title']??'Workshop')?></strong><small><?=h(date('j M Y, H:i',strtotime($sess['starts_at'])))?> · <?=h($sess['location'])?></small></div><?php if(!empty($sess['registration_open'])):?><details class="register-details"><summary class="btn btn-blue small">Request a place ↗</summary><form method="post" action="<?=linkto('workshops')?>" class="register-form"><?php posthidden('register');trap_ui();?><input type="hidden" name="session_id" value="<?=h($sess['id'])?>"><label>Your name<input name="name" required maxlength="120"></label><label>Email<input type="email" name="email" required maxlength="255"></label><label>Notes (optional)<textarea name="notes" maxlength="800" rows="2"></textarea></label><?php consent_ui();?><button class="btn btn-dark small">Send registration request</button></form></details><?php else:?><span>Registration closed</span><?php endif;?></article><?php endforeach;if(!$sessionCount)empty_state('Dates are being arranged','Have a group in mind? Lonny can create a seminar around your needs.');?></div></section>
<section class="section container"><div class="cta-strip"><h2>Have a group, a question, or a wild idea?</h2><a class="btn btn-dark" href="<?=linkto('contact')?>">Let’s talk workshops ↗</a></div></section>
<?php
elseif($page==='watch'):
pagehero('WATCH & LEARN','An inside look at the learning.','Real teaching demonstrations belong here—not stock footage pretending to be Lonny.');
?><section class="section container"><h2 class="subhead">Teaching demonstrations</h2><div class="media-grid"><?php $n=0;foreach($db['videos'] as $vid):if(empty($vid['active']))continue;$n++;$embed=safe_video((string)$vid['url']);?><article class="video-card"><?php if($embed):?><div class="video-frame"><iframe src="<?=h($embed['embed'])?>" title="<?=h($vid['title'])?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div><?php else:?><div class="media-placeholder"><span>▶</span></div><?php endif;?><div class="media-caption"><h3><?=h($vid['title'])?></h3><p><?=h($vid['description'])?></p></div></article><?php endforeach;if(!$n){?><div class="media-placeholder"><span class="play-symbol">▶</span><p>FIRST TEACHING DEMONSTRATION</p><small>Lonny can paste a YouTube or Vimeo link in Admin</small></div><div class="media-placeholder gold"><span class="play-symbol">✳</span><p>A MOMENT FROM A SEMINAR</p><small>Real footage will go here</small></div><?php }?></div><h2 class="subhead gallery-title">Seminar photographs</h2><div class="photo-grid"><?php $p=0;foreach($db['photos'] as $photo):if(empty($photo['active']))continue;$p++;?><figure><img loading="lazy" src="media/<?=h($photo['file'])?>" alt="<?=h($photo['title'])?>"><figcaption><?=h($photo['caption']?:$photo['title'])?></figcaption></figure><?php endforeach;if(!$p){?><div class="photo-placeholder">The best moments<br>are worth sharing. <span>✳</span><small>Lonny’s own photographs will appear here.</small></div><?php }?></div></section>
<?php
elseif($page==='experiment'):
pagehero('A LITTLE DETOUR FROM ORDINARY','The Monthly Experiment','No grades, no textbook, no performance pressure. Just a playful idea to try.');?>
<section class="section container"><div class="experiment-shell"><div class="experiment-intro"><?php eyebrow('90 SECONDS / ZERO PRESSURE');?><h2><?=h($settings['experiment_title'])?></h2><p>Some sounds seem to have shapes. What happens when you trust your first instinct?</p></div><div id="experiment" class="experiment-app" aria-live="polite"><div class="experiment-step"><p class="step-label">PART 1 OF 3</p><h3>Look at these two shapes. Which one should be called <em>KIKI</em>?</h3><div class="shapes" role="group" aria-label="Choose a shape"><button type="button" data-choice="spiky" data-step="1" class="shape-btn"><svg viewBox="0 0 200 200" aria-hidden="true"><path d="M99 7 119 54 170 27 151 82 194 104 147 121 175 172 113 150 88 194 72 140 13 165 51 111 6 78 64 79Z" fill="#E3B85B" stroke="#23383D" stroke-width="5"/></svg><span>A / Spiky</span></button><button type="button" data-choice="round" data-step="1" class="shape-btn"><svg viewBox="0 0 200 200" aria-hidden="true"><path d="M99 12C151 7 187 48 175 88S169 167 114 177 18 162 20 109 51 15 99 12Z" fill="#B8D1A3" stroke="#23383D" stroke-width="5"/></svg><span>B / Rounded</span></button></div></div></div><div class="experiment-foot"><span>Trust your instinct. There’s no personal grade here.</span><span class="little-star">✳</span></div></div></section>
<section class="section container"><div class="explain-card"><h2>What is going on?</h2><p>In sound-symbolism experiments, many people connect sharper-sounding words with angular shapes and softer-sounding words with round shapes. It isn’t a universal rule, and it certainly isn’t a test of intelligence.</p><p>The point is to notice how language can engage feeling, association and perception—not just conscious definitions. This experience is inspired by research on the “bouba/kiki” effect, rather than a formal Suggestopedic exercise by Lonny.</p></div></section>
<?php
elseif($page==='ask'):
pagehero('DEAR LONNY','Ask the question you’ve been sitting on.','About teaching, language, Suggestopedia, creativity, or what makes learning unforgettable.');
?><section class="section container two-col"><div class="form-card"><h2>Write to Lonny</h2><p>Questions are reviewed privately. Lonny may choose to answer one publicly, without publishing your email address.</p><form method="post" action="<?=linkto('ask')?>"><?php posthidden('ask');trap_ui();?><div class="two-fields"><label>Your name <input name="name" maxlength="100" autocomplete="name" placeholder="What should Lonny call you?"></label><label>Your email <input type="email" name="email" maxlength="255" autocomplete="email" required placeholder="you@example.com"></label></div><label>Your question <textarea name="question" maxlength="1600" minlength="8" rows="6" required placeholder="Dear Lonny, why is it that…"></textarea></label><label class="consent"><input name="show_name" type="checkbox" value="1"><span>Lonny may show my first name if he publishes my question.</span></label><?php consent_ui();?><button class="btn btn-blue" type="submit">Send my question ↗</button></form></div><aside class="side-note"><span>?</span><h3>Curiosity is always welcome.</h3><p>Want to challenge an assumption about education? Go ahead.</p></aside></section>
<section class="section container"><div class="section-head"><div><?php eyebrow('ANSWERED BY LONNY');?><h2>Questions worth sharing</h2></div></div><div class="qa-list"><?php $num=0;foreach(array_reverse($db['questions']) as $q):if($q['status']!=='published'||empty($q['answer']))continue;$num++;?><article class="qa-card"><p class="eyebrow">QUESTION<?=(!empty($q['show_name'])&&$q['name'])?' FROM '.h(explode(' ',$q['name'])[0]):''?></p><h3><?=h($q['question'])?></h3><p><?=nl2br(h($q['answer']))?></p></article><?php endforeach;if(!$num)empty_state('The first answer is coming','Send Lonny a thoughtful question. He chooses which ones to share.');?></div></section>
<?php
elseif($page==='playground'):
pagehero('THE PLAYGROUND','Ideas are better when we share them.','A curated wall of unusual words and discoveries from visitors. Every post is reviewed before appearing.');
?><section class="section container"><div class="community-grid"><div><h2>The word wall</h2><div class="response-grid"><?php $c=0;foreach(array_reverse($db['responses']) as $resp):if($resp['status']!=='approved')continue;$c++;?><article class="response-note"><span>✳</span><p><?=nl2br(h($resp['body']))?></p><small>— <?=h($resp['name']?:'Anonymous explorer')?></small></article><?php endforeach;if(!$c)empty_state('The wall is waiting','Be among the first to submit an imaginative new word.');?></div></div><div class="wall-form"><h3>Invent something.</h3><p><?=h($settings['experiment_prompt'])?></p><form method="post" action="<?=linkto('playground')?>"><?php posthidden('respond');trap_ui();?><label>Your display name (optional)<input name="display_name" maxlength="40" placeholder="E.g. The Word Wizard"></label><label>Your invention<textarea name="body" maxlength="400" rows="5" required minlength="3" placeholder="Murmingle: the feeling of…"></textarea></label><?php consent_ui();?><button class="btn btn-blue" type="submit">Submit for review ↗</button></form><p class="micro">Your entry stays private until Lonny approves it.</p></div></div></section>
<?php
elseif($page==='contact'):
pagehero('LET’S MAKE SOMETHING HAPPEN','Bring Lonny to your people.','Tell us about your group or your idea. The experience can be designed together.');
$requested=clean((string)($_GET['format']??''),160);$session_id=clean((string)($_GET['session']??''),40);
?><section class="section container two-col"><div class="form-card"><h2>Start a conversation</h2><p>No automatic dates, prices or promises—just a real enquiry for Lonny to review.</p><form method="post" action="<?=linkto('contact')?>"><?php posthidden('inquire');trap_ui();?><div class="two-fields"><label>Your name <input name="name" maxlength="120" autocomplete="name" required placeholder="Your name"></label><label>Your email <input type="email" name="email" maxlength="255" autocomplete="email" required placeholder="you@example.com"></label></div><label>Organization / school (optional)<input name="organization" maxlength="150" placeholder="Where do you work?"></label><label>Who is this for?<select name="audience"><option value="">Choose an audience</option><option>Teachers</option><option>Organizations</option><option>Learners</option><option>Other</option></select></label><label>Tell Lonny about your idea<textarea name="message" rows="6" maxlength="3000" minlength="10" required placeholder="We’d love a workshop on…"><?=h($requested?'I am interested in: '.$requested."\n\n":'')?><?php if($session_id):?>I would like to request a place at session <?=h($session_id)?>.<?php endif;?></textarea></label><?php consent_ui();?><button class="btn btn-blue" type="submit">Send enquiry ↗</button></form></div><aside class="side-note"><span>✳</span><h3>Different is the whole point.</h3><p>You don't need a perfectly formed brief. A good conversation is a great beginning.</p></aside></section>
<?php
elseif($page==='privacy'):
pagehero('PRIVACY','Your words deserve care.');?><section class="section container privacy-copy"><p class="warning"><strong>Publication checklist:</strong> Lonny must replace this draft with a reviewed privacy notice before launching the site publicly.</p><h2>Who handles your information?</h2><p><?=h($settings['privacy_owner']?:'[Add the legal name and address of the data controller before launch.]')?></p><h2>What is collected?</h2><p>When you send an enquiry or a question, the site stores your name (if provided), email and message. Event registration requests store your contact details and selected session. Community word submissions store only the display name and text you choose.</p><h2>What happens next?</h2><p>The site keeps incoming messages in a private administrator dashboard so Lonny can read and respond. He decides whether to publish selected questions and approved community responses; email addresses are never displayed on public pages. There are no automatic marketing emails.</p><h2>Storage and deletion</h2><p>The site stores data in its hosting environment. The retention period, lawful basis, any processors and international transfers must be completed based on actual deployment. Ask for deletion or access through the contact form until a privacy email is configured.</p><h2>Privacy contact</h2><p><?=h($settings['privacy_contact']?:'[Add a dedicated privacy contact email before launch.]')?></p></section>
<?php
elseif($page==='setup'):
pagehero('ONE-TIME INSTALLATION','Give Lonny the keys.','Only the person with the private installation key can create the first administrator account.');
?><section class="section container narrow"><?php if($db['admin']):?><?php empty_state('Setup is complete','An administrator account already exists. Use the admin sign-in page.');?><a class="btn btn-blue" href="<?=linkto('admin')?>">Go to admin ↗</a><?php else:?><form class="form-card" method="post" action="<?=linkto('setup')?>"><?php posthidden('setup');?><label>Installation key<input type="password" name="setup_key" required placeholder="From SETUP_KEY.txt"></label><label>Administrator email<input type="email" name="email" required autocomplete="username" placeholder="Lonny’s email address"></label><label>Choose a strong password (12 characters minimum)<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><button type="submit" class="btn btn-blue">Create admin account ↗</button><p class="micro">Never upload SETUP_KEY.txt into the public website directory.</p></form><?php endif;?></section>
<?php
elseif($page==='admin'):
include __DIR__.'/../app/admin_view.php';
else: pagehero('404','This door leads nowhere.','Try the menu to find your way back.');
endif;?>
</main><footer class="site-footer"><div class="container footer-top"><div><a class="footer-logo" href="<?=linkto('home')?>">Lonny Gold</a><p>Language, learning and meaningful communication.</p></div><div class="footer-links"><div><strong>Explore</strong><a href="<?=linkto('about')?>">Meet Lonny</a><a href="<?=linkto('workshops')?>">Workshops</a><a href="<?=linkto('watch')?>">Watch & Learn</a></div><div><strong>Get involved</strong><a href="<?=linkto('experiment')?>">Monthly Experiment</a><a href="<?=linkto('ask')?>">Ask Lonny</a><a href="<?=linkto('playground')?>">The Playground</a></div><div><strong>More</strong><a href="<?=linkto('contact')?>">Contact</a><a href="<?=linkto('privacy')?>">Privacy</a><a href="<?=linkto('admin')?>">Lonny’s admin</a></div></div></div><div class="container footer-bottom"><span>© <?=date('Y')?> Lonny Gold.</span><span>Learning through connection</span></div></footer><script src="assets/site.js"></script></body></html>