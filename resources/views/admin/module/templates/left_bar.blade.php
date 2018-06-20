<aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      
      <!-- sidebar menu: : style can be found in sidebar.less -->
      <ul class="sidebar-menu" data-widget="tree">
        <li>
          <a href="{{route('dashboard-user')}}">
            <i class="fa fa-dashboard"></i> <span>{{trans('leftbar.nav.dashboard')}}</span>
          </a>
        </li>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-users"></i> <span>{{trans('leftbar.nav.employee')}}</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="{{ asset('employee')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.list.employee')}}</a></li>
            <li><a href="{{ asset('employee/create')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.add.employee')}}</a></li>
          </ul>
        </li>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-handshake-o"></i> <span>{{trans('leftbar.nav.vendor')}}</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="{{ asset('vendors')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.list.vendor')}}</a></li>
            <li><a href="{{ asset('vendors/create')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.add.vendor')}}</a></li>
          </ul>
        </li>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-heartbeat"></i> <span>{{trans('leftbar.nav.team')}}</span>
              <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="{{ asset('teams')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.list.team')}}</a></li>
            <li><a href="{{ asset('teams/create')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.add.team')}}</a></li>
          </ul>
        </li>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-diamond"></i> <span>{{trans('leftbar.nav.project')}}</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="{{ asset('projects')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.list.project')}}</a></li>
            <li><a href="{{ asset('projects/create')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.add.project')}}</a></li>
          </ul>
        </li>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-diamond"></i> <span>{{trans('leftbar.nav.absence')}}</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="{{ asset('absences')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.list.absence')}}</a></li>
            <li><a href="{{ asset('absences/create')}}"><i class="fa fa-circle-o"></i>{{trans('leftbar.nav.add.absence')}}</a></li>
          </ul>
        </li>
                
      </ul>

    </section>
    <!-- /.sidebar -->
  </aside>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script>
    var url = window.location.href;
    // var path = window.location.pathname;
    $(document).ready(function () {
        // alert(url);
        var path = url.split('/')[3];
        $('.sidebar-menu li').each(function () {
            var href = $(this).find('a').attr('href');

            if(url == href || url.split('?')[0] == href){
                $(this).addClass('active');
                $(this).find('i').attr('class', 'fa fa-bullseye');
                $(this).parent().css('display', 'block');
                $(this).parent().parent().addClass('menu-open active');
                return false;
            }
            else if(href.split('/').length > 1){
                if(path == href.split('/')[3]){
                    $(this).parent().parent().addClass('active');
                    $(this).parent().css('display', 'none');
                }
            }
        });
    });
</script>
  