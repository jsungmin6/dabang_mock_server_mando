<?php

//READ
function test()
{
    $pdo = pdoSqlConnect();
    $query = "SELECT * FROM Test;";

    $st = $pdo->prepare($query);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function recommendSecondRoomList($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$latitude,$longitude,$scale,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select R.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,'㎡'), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(A.quickInquiry,'N') as quickInquiry,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart
from Room as R
             left join RoomInComplex as RC
                   on RC.roomIdx = R.roomIdx
         left join Complex as C
                   on RC.complexIdx = C.complexIdx
         left join AgencyRoom as AR
                   on AR.roomIdx = RC.roomIdx
         left join Agency as A
                   on AR.agencyIdx = A.agencyIdx
         left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx
) as UH
                   on UH.roomIdx = RC.roomIdx
         left join (select RI.roomIdx, R.roomImg
                    from (select min(roomImgIdx) as roomImgIdx, roomIdx
                          from RoomImg
                          group by roomIdx) as RI
                             left join RoomImg as R
                                       on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
                   on RI.roomIdx = RC.roomIdx
where R.kindOfRoom regexp :roomType and SUBSTRING_INDEX(SUBSTRING_INDEX(R.maintenanceCost, ' ', -1),'만',1) >= :maintenanceCostMin and SUBSTRING_INDEX(SUBSTRING_INDEX(R.maintenanceCost, ' ', -1),'만',1) <= :maintenanceCostMax
and left(R.exclusiveArea, char_length(R.exclusiveArea)-1) >= :exclusiveAreaMin and left(R.exclusiveArea, char_length(R.exclusiveArea)-1) <= :exclusiveAreaMax
and R.isDeleted = 'N'
and R.latitude >= (:latitude-(:scale/1000))
and R.latitude <= (:latitude+(:scale/1000))
and R.longitude >= (:longitude-(:scale/1000))
and R.longitude <= (:longitude+(:scale/1000))
order by rand()
limit 10";


    $st = $pdo->prepare($query1);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',floor($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',ceil($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',floor($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',ceil($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',intval($scale),PDO::PARAM_INT);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);

    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;
    }

    $st = null;
    $pdo = null;

    return $result;

}

function recommendRoomNum($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$address)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*)
from Room as R
where R.kindOfRoom regexp :roomType and SUBSTRING_INDEX(SUBSTRING_INDEX(R.maintenanceCost, ' ', -1),'만',1) >= :maintenanceCostMin and SUBSTRING_INDEX(SUBSTRING_INDEX(R.maintenanceCost, ' ', -1),'만',1) <= :maintenanceCostMax
and left(R.exclusiveArea, char_length(R.exclusiveArea)-1) >= :exclusiveAreaMin and left(R.exclusiveArea, char_length(R.exclusiveArea)-1) <= :exclusiveAreaMax
and R.roomAddress Like concat('%',:address,'%') and R.isDeleted = 'N'
order by R.checkedRoom, R.plus desc";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType', $roomType, PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin', floor($maintenanceCostMin), PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax', ceil($maintenanceCostMax), PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin', floor($exclusiveAreaMin), PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax', ceil($exclusiveAreaMax), PDO::PARAM_INT);
    $st->bindParam(':address', $address, PDO::PARAM_STR);

    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function recommendRoomList($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$address,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select R.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,'㎡'), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(A.quickInquiry,'N') as quickInquiry,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart
from Room as R
             left join RoomInComplex as RC
                   on RC.roomIdx = R.roomIdx
         left join Complex as C
                   on RC.complexIdx = C.complexIdx
         left join AgencyRoom as AR
                   on AR.roomIdx = RC.roomIdx
         left join Agency as A
                   on AR.agencyIdx = A.agencyIdx
         left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx
) as UH
                   on UH.roomIdx = RC.roomIdx
         left join (select RI.roomIdx, R.roomImg
                    from (select min(roomImgIdx) as roomImgIdx, roomIdx
                          from RoomImg
                          group by roomIdx) as RI
                             left join RoomImg as R
                                       on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
                   on RI.roomIdx = RC.roomIdx
where R.kindOfRoom regexp :roomType and SUBSTRING_INDEX(SUBSTRING_INDEX(R.maintenanceCost, ' ', -1),'만',1) >= :maintenanceCostMin and SUBSTRING_INDEX(SUBSTRING_INDEX(R.maintenanceCost, ' ', -1),'만',1) <= :maintenanceCostMax
and left(R.exclusiveArea, char_length(R.exclusiveArea)-1) >= :exclusiveAreaMin and left(R.exclusiveArea, char_length(R.exclusiveArea)-1) <= :exclusiveAreaMax
and R.roomAddress Like concat('%',:address,'%') and R.isDeleted = 'N'
order by rand()
limit 5";


    $st = $pdo->prepare($query1);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',floor($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',ceil($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',floor($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',ceil($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);

    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;
    }

    $st = null;
    $pdo = null;

    return $result;

}


function getExclusiveAreaMax($userIdx)
{

    $exclusiveAreaMaxAvg=getExclusiveAreaMaxAvg($userIdx);
    $exclusiveAreaMaxStd=getExclusiveAreaMaxStd($userIdx);

    $rangeMin=$exclusiveAreaMaxAvg-(1.5)*$exclusiveAreaMaxStd;
    $rangeMax=$exclusiveAreaMaxAvg+(1.5)*$exclusiveAreaMaxStd;

    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(exclusiveAreaMax),2),1000) as exclusiveAreaMax from UserSearchLog
where exclusiveAreaMax >=:rangeMin and exclusiveAreaMax <=:rangeMax";

    $st = $pdo->prepare($query);
    $st->bindParam(':rangeMin',floor($rangeMin),PDO::PARAM_INT);
    $st->bindParam(':rangeMax',ceil($rangeMax),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}



function getExclusiveAreaMin($userIdx)
{

    $exclusiveAreaMinAvg=getExclusiveAreaMinAvg($userIdx);
    $exclusiveAreaMinStd=getExclusiveAreaMinStd($userIdx);

    $rangeMin=$exclusiveAreaMinAvg-(1.5)*$exclusiveAreaMinStd;
    $rangeMax=$exclusiveAreaMinAvg+(1.5)*$exclusiveAreaMinStd;


    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(exclusiveAreaMin),2),0) as exclusiveAreaMin from UserSearchLog
where exclusiveAreaMin >=:rangeMin and exclusiveAreaMin <=:rangeMax and exclusiveAreaMin !=0 ";

    $st = $pdo->prepare($query);
    $st->bindParam(':rangeMin',floor($rangeMin),PDO::PARAM_INT);
    $st->bindParam(':rangeMax',ceil($rangeMax),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getMaintenaceCostMax($userIdx)
{

    $maintenanceCostMaxAvg=getMaintenanceCostMaxAvg($userIdx);
    $maintenanceCostMaxStd=getMaintenanceCostMaxStd($userIdx);

    $rangeMin=$maintenanceCostMaxAvg-(1.5)*$maintenanceCostMaxStd;
    $rangeMax=$maintenanceCostMaxAvg+(1.5)*$maintenanceCostMaxStd;

    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(maintenanceCostMax),2),1000) as maintenanceCostMax from UserSearchLog
where maintenanceCostMax >=:rangeMin and maintenanceCostMax <=:rangeMax";

    $st = $pdo->prepare($query);
    $st->bindParam(':rangeMin',floor($rangeMin),PDO::PARAM_INT);
    $st->bindParam(':rangeMax',ceil($rangeMax),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getMaintenaceCostMin($userIdx)
{

    $maintenanceCostMinAvg=getMaintenanceCostMinAvg($userIdx);
    $maintenanceCostMinStd=getMaintenanceCostMinStd($userIdx);

    $rangeMin=$maintenanceCostMinAvg-(1.5)*$maintenanceCostMinStd;
    $rangeMax=$maintenanceCostMinAvg+(1.5)*$maintenanceCostMinStd;

    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(maintenanceCostMin),2),0) as maintenanceCostMin from UserSearchLog
where maintenanceCostMin >=:rangeMin and maintenanceCostMin <=:rangeMax and maintenanceCostMin !=0";

    $st = $pdo->prepare($query);
    $st->bindParam(':rangeMin',floor($rangeMin),PDO::PARAM_INT);
    $st->bindParam(':rangeMax',ceil($rangeMax),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function getExclusiveAreaMaxStd($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(STD(exclusiveAreaMax),1),0) as maintenanceCostMin from UserSearchLog
where userIdx=:userIdx and exclusiveAreaMax !=1000";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getExclusiveAreaMinStd($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(STD(exclusiveAreaMin),2),1000) as exclusiveAreaMin from UserSearchLog
where userIdx=:userIdx and exclusiveAreaMin !=0";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getMaintenanceCostMaxStd($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(STD(maintenanceCostMax),2),1000) as maintenanceCostMin from UserSearchLog
where userIdx=:userIdx and maintenanceCostMax !=1000";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getMaintenanceCostMinStd($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(STD(maintenanceCostMin),2),0) as maintenanceCostMin from UserSearchLog
where userIdx=:userIdx and maintenanceCostMin !=0;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getExclusiveAreaMaxAvg($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(exclusiveAreaMax),1),0) as maintenanceCostMin from UserSearchLog
where userIdx=:userIdx and exclusiveAreaMax !=1000";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getExclusiveAreaMinAvg($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(exclusiveAreaMin),2),0) as exclusiveAreaMin from UserSearchLog
where userIdx=:userIdx and exclusiveAreaMin !=0";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function getMaintenanceCostMaxAvg($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(maintenanceCostMax),2),1000) as maintenanceCostMin from UserSearchLog
where userIdx=:userIdx and maintenanceCostMax !=1000";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function getMaintenanceCostMinAvg($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(ROUND(AVG(maintenanceCostMin),2),0) as maintenanceCostMin from UserSearchLog
where userIdx=:userIdx and maintenanceCostMin !=0;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function getRecommendAddress($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select A.searchLog ,A.searchRate+A.likeRate+A.inquiryRate+A.callRate as rate from(
select U.searchLog, U.searchRate, COALESCE(L.likeRate,0) as likeRate, COALESCE(I.inquiryRate,0) as inquiryRate, COALESCE(C.callRate,0) as callRate
from (select searchLog,count(searchlog) as searchRate from UserSearchLog
where userIdx=:userIdx and createdAt >= DATE_SUB(NOW(), INTERVAL 10 DAY )
group by searchLog) as U
left join (select R.roomAddress, count(R.roomAddress)*1.2 as likeRate
from (select roomIdx from UserHeart
where userIdx=:userIdx and heart='Y' and isDeleted ='N' and updatedAt >= DATE_SUB(NOW(), INTERVAL 10 DAY) and roomIdx is not null) as U
left join Room as R
on U.roomIdx = R.roomIdx
group by R.roomAddress) as L
on U.searchLog=L.roomAddress
left join (select R.roomAddress, count(R.roomAddress)*1.4 as inquiryRate
from (select roomIdx from UserInquiryLog
where userIdx=:userIdx and isDeleted ='N' and createdAt >= DATE_SUB(NOW(), INTERVAL 10 DAY )) as U
left join Room as R
on U.roomIdx = R.roomIdx
group by R.roomAddress) as I
on I.roomAddress = U.searchLog
left join (select R.roomAddress, count(R.roomAddress)*1.8 as callRate
from (select roomIdx from UserCallLog
where userIdx=:userIdx and isDeleted ='N' and createdAt >= DATE_SUB(NOW(), INTERVAL 10 DAY )) as U
left join Room as R
on U.roomIdx = R.roomIdx
group by R.roomAddress) as C
on C.roomAddress = U.searchLog) as A
limit 1;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0]['searchLog'];
}

function getRecommendRoomType($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select '원룸' as roomType, count(*) as rate
from(select roomType from UserSearchLog                                
where userIdx=:userIdx and roomType not in ('원룸|투쓰리룸|오피스텔')) as R      
where R.roomType like '%원룸%'
union
select '투룸' as roomType, count(*) as rate
from(select roomType from UserSearchLog
where userIdx=:userIdx and roomType not in ('원룸|투쓰리룸|오피스텔')) as R
where R.roomType like '%투룸%'
union
select '쓰리룸' as roomType, count(*) as rate
from(select roomType from UserSearchLog
where userIdx=:userIdx and roomType not in ('원룸|투쓰리룸|오피스텔')) as R
where R.roomType like '%쓰리룸%'
union
select '오피스텔' as roomType, count(*) as rate
from(select roomType from UserSearchLog
where userIdx=:userIdx and roomType not in ('원룸|투쓰리룸|오피스텔')) as R
where R.roomType like '%오피스텔%'
order by rate desc
limit 1";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0]['roomType'];
}

function selectSearchWord($rows,$pages)
{
    $offset = $rows*($pages-1);
    $pdo = pdoSqlConnect();
    $query = "select searchWord, nowRate, diff from RealTimeSearchWord
where isDeleted ='N'
order by nowRate desc, diff desc
LIMIT :limit offset :offset";


    $st = $pdo->prepare($query);
    $st->bindParam(':limit',intval($rows),PDO::PARAM_INT);
    $st->bindParam(':offset',intval($offset),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;

}


function createSearchWord()
{
    $pdo = pdoSqlConnect();

    try {
        $pdo->beginTransaction();
        $query1 = "delete from RealTimeSearchWord;";

        $st = $pdo->prepare($query1);
        $st->execute();

        $query2 = "Insert Into RealTimeSearchWord (searchWord, nowRate, diff)
select R.searchWord, R.nowRate, R.diff
from (select N.searchWord, N.nowRate, COALESCE(S.subRate,0) as subRate, COALESCE(nowRate-subrate,N.nowRate) as diff
from (select searchLog as searchWord, count(searchLog) as nowRate from UserSearchLog
where createdAt >= DATE_SUB(NOW(), INTERVAL 3 DAY)
group by searchLog) as N
left join (select searchLog as searchWord, count(searchLog) as subrate from UserSearchLog
where createdAt >= DATE_SUB(NOW(), INTERVAL 3 DAY ) and createdAt <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
group by searchLog) as S
on S.searchWord = N.searchWord) as R;
";

        $st = $pdo->prepare($query2);
        $st->execute();

        $pdo->commit();
    }
    catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
            // If we got here our two data updates are not in the database
        }
        throw $e;
    }
}


function userInfoCreate($userEmail)
{
    $pdo = pdoSqlConnect();
    $query = "SELECT userIdx FROM User WHERE userEmail = ?;";

    $st = $pdo->prepare($query);
    $st->execute([$userEmail]);
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0];
}

function createUser($userName, $userEmail, $userPwd, $userPhone)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO User (userName, userEmail, userPwd, userPhone) VALUES (?, ? ,?, ?);";

    $st = $pdo->prepare($query);
    $st->execute([$userName, $userEmail, $userPwd, $userPhone]);

    $st = null;
    $pdo = null;

}

function createSnsUser($userName, $userEmail)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO User (userName, userEmail, snsType) VALUES (?, ? ,1);";

    $st = $pdo->prepare($query);
    $st->execute([$userName, $userEmail]);

    $st = null;
    $pdo = null;

}


function userInfo($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(userName,'null') as userName,
       COALESCE(userEmail,'null') as userEmail,
       COALESCE(userProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as userProfileImg,
       COALESCE(userPhone,'null') as userPhone
from User
where userIdx = :userIdx
  and isDeleted = 'N';";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}



function userAgencyCall($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select URL.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만원') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(A.agencyIdx,'null') as agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(concat(ARN.agencyRoomNum,'개의 방'),'0개의 방') as agencyRoomNum,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart,'N') as heart
from (select Max(userCallIdx) as userCallIdx, roomIdx from UserCallLog
where userIdx=:userIdx and isDeleted=\"N\"
group by roomIdx) as URL
left join UserCallLog as URL2
on URL2.userCallIdx = URL.userCallIdx and URL2.roomIdx = URL.roomIdx
left join RoomInComplex as RIC
on URL.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join Room as R
on URL.roomIdx = R.roomIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = URL.roomIdx
left join AgencyRoom as AR
on AR.roomIdx = URL.roomIdx
left join Agency as A
on AR.agencyIdx = A.agencyIdx
left join AgencyMember as AM
on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition=\"대표공인중개사\"
left join (select agencyIdx, count(agencyIdx) as agencyRoomNum from AgencyRoom
group by agencyIdx) as ARN
on ARN.agencyIdx = AR.agencyIdx
left join (select roomIdx, heart from UserHeart where isDeleted =\"N\" and roomIdx is not null and userIdx = :userIdx) as UH
on UH.roomIdx = URL.roomIdx
order by URL2.createdAt desc
";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;
    }

    $st = null;
    $pdo = null;

    return $result;

}


function userRoomInquiry($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select URL.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만원') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart,
       URL2.createdAt as inquiryTime
from (select Max(userInquiryLogIdx) as userInquiryLogIdx, roomIdx from UserInquiryLog
where userIdx=:userIdx and isDeleted=\"N\"
group by roomIdx) as URL
left join UserInquiryLog as URL2
on URL2.userInquiryLogIdx = URL.userInquiryLogIdx and URL2.roomIdx = URL.roomIdx
left join RoomInComplex as RIC
on URL.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join Room as R
on URL.roomIdx = R.roomIdx
left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx) as UH
on UH.roomIdx = URL.roomIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = URL.roomIdx
order by URL2.createdAt desc
";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;
    }

    $st = null;
    $pdo = null;

    return $result;

}



function userComplexLike($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(UC.complexIdx,\"null\") as complexIdx,
       COALESCE(C.complexName,\"null\") as complexName,
       COALESCE(C.complexAddress,\"null\") as complexAddress,
       COALESCE(CI.complexImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg,
       COALESCE(RN.roomNum,\"0\") as roomNum,
       COALESCE(C.kindOfBuilding,\"null\") as roomType,
       COALESCE(concat(C.householdNum,'세대'),\"null\") as householdNum,
       COALESCE(C.completionDate,\"null\") as completionDate
from (select complexIdx, updatedAt
      from UserHeart
      where userIdx = :userIdx
        and complexIdx is not null
        and heart = \"Y\"
        and isDeleted = \"N\") as UC
         left join Complex as C
                   on C.complexIdx = UC.complexIdx
         left join (select C.complexIdx, C.complexImg
                    from (select min(complexImgIdx) as complexImgIdx, complexIdx
                          from ComplexImg
                          group by complexIdx) as CI
                             left join ComplexImg as C
                                       on C.complexImgIdx = CI.complexImgIdx and C.complexIdx = CI.complexIdx) as CI
                   on CI.complexIdx = UC.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = UC.complexIdx
order by UC.updatedAt desc
";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;

}



function roomCompare($roomIdx1,$roomIdx2,$roomIdx3)
{
    $pdo = pdoSqlConnect();
    $query = "select URL.roomIdx,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       COALESCE(concat(R.contractArea,\"㎡\"), 'null') as contractArea,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(R.buildingFloor,'null') as buildingFloor,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만원') end as maintenanceCost,
       COALESCE(R.parking,'null') as parking,
       COALESCE(R.shortTermRental,'null') as shortTermRental,
       COALESCE(O.options,'null') as options,
       COALESCE(S.security,'null') as security,
       COALESCE(R.kindOfHeating,'null') as kindOfHeating,
       COALESCE(R.builtIn,'null') as builtIn,
       COALESCE(R.elevator,'null') as elevator,
       COALESCE(R.pet,'null') as pet,
       COALESCE(R.balcony,'null') as balcony,
       COALESCE(R.rentSubsidy,'null') as rentSubsidy,
       COALESCE(R.moveInDate,'null') as moveInDate,
       COALESCE(A.agencyIdx,'null') as agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyBossName,'null') as agencyBossName,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(A.agencyBossPhone,'null') as agencyBossPhone
from (select roomIdx from Room where roomIdx = :roomIdx1 or roomIdx = :roomIdx2 or roomIdx = :roomIdx3) as URL
left join RoomInComplex as RIC
on URL.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join Room as R
on URL.roomIdx = R.roomIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = URL.roomIdx
left join AgencyRoom as AR
on URL.roomIdx = AR.roomIdx
left join Agency as A
on AR.agencyIdx = A.agencyIdx
left join AgencyMember as AM
on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition=\"대표공인중개사\"
left join (select RI.roomIdx, GROUP_CONCAT(I.iconName) as options from RoomIcon as RI
left join Icon as I
on I.iconIdx = RI.iconIdx
where I.iconType = \"옵션\"
group by RI.roomIdx) as O
on O.roomIdx = URL.roomIdx
left join (select RI.roomIdx, GROUP_CONCAT(I.iconName) as security from RoomIcon as RI
left join Icon as I
on I.iconIdx = RI.iconIdx
where I.iconType = \"보안/안전시설\"
group by RI.roomIdx) as S
on S.roomIdx = URL.roomIdx
";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomIdx1',$roomIdx1,PDO::PARAM_STR);
    $st->bindParam(':roomIdx2',$roomIdx2,PDO::PARAM_STR);
    $st->bindParam(':roomIdx3',$roomIdx3,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function userRoomLike($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select URL.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만원') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart,
       COALESCE(R.sold, 'N') as sold,
       COALESCE(R.isDeleted, 'N') as isDeleted,
       COALESCE(R.open, 'Y') as open
from (select updatedAt, roomIdx from UserHeart
where userIdx=:userIdx and isDeleted=\"N\" and roomIdx is not null and heart =\"Y\") as URL
left join RoomInComplex as RIC
on URL.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join Room as R
on URL.roomIdx = R.roomIdx
left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx) as UH
on UH.roomIdx = URL.roomIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = URL.roomIdx
order by URL.updatedAt desc
";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;
    }

    $st = null;
    $pdo = null;

    return $result;
}



function userComplexView($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(UC.complexIdx,\"null\") as complexIdx,
       COALESCE(C.complexName,\"null\") as complexName,
       COALESCE(C.complexAddress,\"null\") as complexAddress,
       COALESCE(CI.complexImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg,
       COALESCE(RN.roomNum,\"0\") as roomNum,
       COALESCE(C.kindOfBuilding,\"null\") as roomType,
       COALESCE(concat(C.householdNum,'세대'),\"null\") as householdNum,
       COALESCE(C.completionDate,\"null\") as completionDate
from (select Max(createdAt) as createdAt, complexIdx from UserComplexLog
where userIdx=:userIdx and isDeleted=\"N\"
group by complexIdx) as UC
         left join Complex as C
                   on C.complexIdx = UC.complexIdx
         left join (select C.complexIdx, C.complexImg
                    from (select min(complexImgIdx) as complexImgIdx, complexIdx
                          from ComplexImg
                          group by complexIdx) as CI
                             left join ComplexImg as C
                                       on C.complexImgIdx = CI.complexImgIdx and C.complexIdx = CI.complexIdx) as CI
                   on CI.complexIdx = UC.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = UC.complexIdx
order by UC.createdAt desc";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;
    }

    $st = null;
    $pdo = null;

    return $result;
}



function userRoomView($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "
select URL.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart,
       COALESCE(R.sold, 'N') as sold,
       COALESCE(R.isDeleted, 'N') as isDeleted,
       COALESCE(R.open, 'Y') as open
from (select Max(userRoomLogIdx) as userRoomLogIdx, roomIdx from UserRoomLog
where userIdx=:userIdx and isDeleted=\"N\"
group by roomIdx) as URL
left join UserRoomLog as URL2
on URL2.userRoomLogIdx = URL.userRoomLogIdx and URL2.roomIdx = URL.roomIdx
left join RoomInComplex as RIC
on URL.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join Room as R
on URL.roomIdx = R.roomIdx
left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx) as UH
on UH.roomIdx = URL.roomIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = URL.roomIdx
order by URL2.createdAt desc";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}

function deleteSearchRecord($userIdx){
    $pdo = pdoSqlConnect();
    $query = "update UserSearchLog
set isDeleted=\"Y\"
where userIdx = :userIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st = null;
    $pdo = null;
}


function searchRecently($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select C.regionName as regionName,
       COALESCE(C.address,'null') as address,
       COALESCE(C.tag,'null') as tag,
       case when regionName like '%동' then 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EC%A7%80%EC%97%AD%EB%AA%85%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=9cd01fe3-122b-4faa-86b5-0af71919afd4'
       when regionName like '%역' then 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EC%97%AD%20%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=6ea88cf0-e8f7-45cd-9459-1819aaf0b73a'
       when regionName like '%교' then 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EB%8C%80%ED%95%99%EA%B5%90%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=d19e2c9c-c68e-4098-adbc-f1499727122a'
       else 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EC%95%84%ED%8C%8C%ED%8A%B8%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=b67cb97f-0174-4828-b538-8c1954fb732b'
       end as icon,
       COALESCE(Cp.complexIdx,'null') as complexIdx
from((select C.searchLog as regionName , null as address , null as tag, C.createdAt as createdAt
from (select searchLog,Max(createdAt) as createdAt from UserSearchLog where isDeleted=\"N\" group by searchLog) as C
where searchlog like '%동')
union
(select R.regionName, R.address, S.stationLine as tag, R.createdAt  from
(select searchlog as regionName , null as address , createdAt
from (select searchLog,Max(createdAt) as createdAt from UserSearchLog where isDeleted=\"N\" group by searchLog) as C
where searchlog like '%역') as R
left join Station as S
on S.stationName = R.regionName)
union
(select R.regionName, C.complexAddress, C.kindOfbuilding, R.createdAt from
(select C.searchlog as regionName , null as address , null as tag, C.createdAt from (select searchLog,Max(createdAt) as createdAt from UserSearchLog where isDeleted=\"N\" group by searchLog) as C
where not searchlog like  '%역' and not searchlog like  '%동' and not searchlog like  '%읍' and not searchlog like  '%면') as R
left join Complex as C
on C.complexName = R.regionName)) as C
left join Complex as Cp
on regionName = Cp.ComplexName
order by C.createdAt desc
limit 10
";

    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function searchList($keyWord)
{
    $pdo = pdoSqlConnect();
    $query = "select C.regionName as regionName,
       COALESCE(C.address,'null') as address,
       COALESCE(C.tag,'null') as tag,
       case when regionName like '%동' then 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EC%A7%80%EC%97%AD%EB%AA%85%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=9cd01fe3-122b-4faa-86b5-0af71919afd4'
       when regionName like '%역' then 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EC%97%AD%20%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=6ea88cf0-e8f7-45cd-9459-1819aaf0b73a'
       when regionName like '%교' then 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EB%8C%80%ED%95%99%EA%B5%90%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=d19e2c9c-c68e-4098-adbc-f1499727122a'
       else 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/icon%2F%EC%95%84%ED%8C%8C%ED%8A%B8%EC%95%84%EC%9D%B4%EC%BD%98.PNG?alt=media&token=b67cb97f-0174-4828-b538-8c1954fb732b'
       end as icon,
       COALESCE(Cp.complexIdx,'null') as complexIdx
from(
(select complexName as regionName , complexAddress as address , kindOfBuilding as tag from Complex where isDeleted='N')
union
(select dongAddress as regionName, null as address, null as tag from Region where isDeleted='N')
union
(select universityName as regionName, null as address, null as tag from University where isDeleted='N')
union
(select stationName as regionName, null as address, stationLine as tag from Station where isDeleted='N')) as C
left join Complex as Cp
on C.regionName = Cp.ComplexName
where C.regionName like concat('%',:keyWord,'%')
order by icon desc
limit 30";

    $st = $pdo->prepare($query);
    $st->bindParam(':keyWord',$keyWord,PDO::PARAM_STR);
    //    $st->execute([$param,$param]);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function homeEvent()
{
    $pdo = pdoSqlConnect();
    $query = "select homeEventImg, homeEventUrl from HomeEvent
where isDeleted=\"N\"
order by createdAt desc
limit 5";

    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function homeContent()
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(postImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as postImg,
       COALESCE(postUrl,\"null\") as postUrl,
       COALESCE(postTitle,\"null\") as postTitle,
       COALESCE(FORMAT(postViewCount , 0),\"0\") as postViewCount
from NaverPost
where isDeleted = \"N\"
order by createdAt desc
limit 5";

    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function homeComplexInterest($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select URL.complexIdx,
       COALESCE(C.complexName, \"null\")     as complexName,
       COALESCE(CI.complexImg, \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg,
       COALESCE(concat(RN.roomNum,'개의 방'), \"0개의 방\")     as roomNum,
       COALESCE(C.kindOfBuilding, \"null\")     as kindOfBuilding,
       COALESCE(concat(C.householdNum,'세대'), \"null\")     as householdNum,
       COALESCE(C.completionDate, \"null\")     as completionDate
from (select Max(userComplexLogIdx) as userComplexLogIdx, complexIdx from UserComplexLog
where userIdx=:userIdx and isDeleted=\"N\"
group by complexIdx) as URL
left join UserComplexLog as URL2
on URL2.userComplexLogIdx = URL.userComplexLogIdx and URL2.complexIdx = URL.complexIdx
left join Complex as C
on C.complexIdx = URL.complexIdx
left join (select COM.complexName,
                           COM.kindOfBuilding,
                           COM.householdNum,
                           COM.completionDate,
                           CI.complexIdx,
                           CI.complexImg as complexImg
                    from (select complexIdx, Max(createdAt) as createdAt
                          from ComplexImg
                          group by complexIdx) as C
                             left join ComplexImg as CI
                                       on CI.complexIdx = CI.complexIdx and C.createdAt = CI.createdAt
                             left join Complex as COM
                                       on COM.complexIdx = CI.complexIdx
) as CI
                   on CI.complexIdx = C.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = C.complexIdx
order by URL2.createdAt desc
limit 5";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function homeRoomInterest($userIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select U.searchLog as regionName,
       COALESCE(concat(RN.roomNum,'개의 방'),'0개의 방') as roomNum,
       COALESCE(R.regionImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as regionImg,
       replace(replace(U.roomType,'투쓰리룸','투ㆍ쓰리룸'),'|',',') as roomType
from (select U.searchLog, U.createdAt, R.roomType from
     (select searchLog, Max(createdAt) as createdAt
      from UserSearchLog
      where userIdx = :userIdx
    group by searchLog) as U
left join UserSearchLog as R
on R.searchLog = U.searchLog and R.createdAt = U.createdAt) as U
         left join (select dongAddress as region, dongImg as regionImg from Region
union
select stationName as region, stationImg as regionImg from Station
union
select universityName as region, universityImg as regionImg from University) as R
                   on R.region = U.searchLog
         left join (select roomAddress as region,
                           count(roomAddress) as roomNum
                    from Room
                    group by roomAddress
union
select S.stationName as region ,count(S.stationName) as roomNum from Station as S, Room as R
where (select Round(6371 * acos(cos(radians((S.latitude))) *
                        cos(radians(R.latitude)) * cos(radians(R.longitude) - radians(
                (S.longitude))) +
                        sin(radians((S.latitude))) *
                        sin(radians(R.latitude))), 0)) <=1
group by S.stationName
union
select S.universityName as region ,count(S.universityName) as roomNum from University as S, Room as R
where (select Round(6371 * acos(cos(radians((S.latitude))) *
                        cos(radians(R.latitude)) * cos(radians(R.longitude) - radians(
                (S.longitude))) +
                        sin(radians((S.latitude))) *
                        sin(radians(R.latitude))), 0)) <=1
group by S.universityName) as RN
                   on R.region = RN.region
where U.searchLog Like '%동' or U.searchLog Like '%면' or  U.searchLog Like '%읍' or  U.searchLog Like '%역' or U.searchLog Like '%교'
order by U.createdAt desc
limit 5
";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


//READ
function roomDetail($roomIdx,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select COALESCE(U.heart,'N') as heart,
       R.roomIdx,
       COALESCE(RIC.complexIdx, \"null\") as complexIdx,
       COALESCE(R.sold,'N') as sold,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(R.roomSummary, \"null\") as roomSummary,
       COALESCE(SN.securityNum, \"0\") as securityNum,
       COALESCE(R.monthlyRent, \"null\") as monthlyRent,
       COALESCE(R.lease, \"null\") as lease,
       COALESCE(R.maintenanceCost, \"null\") as maintenanceCost,
       COALESCE(R.parking, \"null\") as parking,
       COALESCE(R.shortTermRental, \"null\") as shortTermRental,
       COALESCE(R.monthlyLivingExpenses, \"null\") as monthlyLivingExpenses,
       COALESCE(R.kindOfRoom, \"null\") as kindOfRoom,
       COALESCE(R.thisFloor, \"null\") as thisFloor,
       COALESCE(R.buildingFloor, \"null\") as buildingFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), \"null\") as exclusiveArea,
       COALESCE(concat(R.contractArea,\"㎡\"), \"null\") as contractArea,
       COALESCE(R.kindOfHeating, \"null\") as kindOfHeating,
       COALESCE(R.builtIn, \"null\") as builtIn,
       COALESCE(concat(DATE_FORMAT(R.completionDate,\"%Y.%m\"),\" 준공\"), \"null\") as completionDate,
       COALESCE(concat(R.householdNum,\"세대\"), \"null\") as householdNum,
       COALESCE(concat(R.parkingPerHousehold,\"대\"), \"null\") as parkingPerHousehold,
       COALESCE(R.elevator, \"null\") as elevator,
       COALESCE(R.pet, \"null\") as pet,
       COALESCE(R.balcony, \"null\") as balcony,
       COALESCE(R.rentSubsidy, \"null\") as rentSubsidy,
       COALESCE(R.moveInDate, \"null\") as moveInDate,
       R.latitude,
       R.longitude,
       R.roomAddress,
       COALESCE(R.score, \"null\") as score,
       COALESCE(R.scoreComment, \"null\") as scoreComment,
       COALESCE(R.scoreImg, \"null\") as scoreImg,
       COALESCE(R.description, \"null\") as description,
       COALESCE(A.agencyIdx, \"null\") as agencyIdx,
       COALESCE(A.agencyBossPhone, \"null\") as agencyBossPhone,
       COALESCE(A.agencyBossName, \"null\") as agencyBossName,
       COALESCE(A.agencyAddressDetail, \"null\") as agencyAddress,
       COALESCE(A.agencyName, \"null\") as agencyName,
       COALESCE(A.agencyMemberName, \"null\") as agencyMemberName,
       COALESCE(A.agencyMemberPosition, \"null\") as agencyMemberPosition,
       COALESCE(A.agencyMemberProfileImg, \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914\") as agencyMemberProfileImg,
       COALESCE(A.agencyMemberPhone, \"null\") as agencyMemberPhone,
       COALESCE(A.quickInquiry, \"null\") as complexIdx
from (select * from Room where roomIdx = :roomIdx and isDeleted = \"N\") as R
         left join (SELECT userIdx,roomIdx,heart FROM UserHeart
where userIdx = :userIdx
) as U
                   on R.roomIdx = U.roomIdx
         left join RoomInComplex as RIC
                   on RIC.roomIdx = R.roomIdx
         left join Complex as C
                   on RIC.complexIdx = C.complexIdx
         left join (select R.roomIdx, R.iconType, count(R.iconType) as securityNum
                    from (
                             select R.roomIdx, I.iconType
                             from RoomIcon as R
                                      left join Icon as I
                                                on I.iconIdx = R.iconIdx
                             where R.roomIdx = :roomIdx) as R
                    group by R.iconType
                    Having R.iconType = \"보안/안전시설\") as SN
                   on SN.roomIdx = R.roomIdx
         left join (select AR.roomIdx,
                           A.agencyIdx,
                           A.agencyBossPhone,
                           A.agencyBossName,
                           A.agencyAddressDetail,
                           A.agencyName,
                           A.quickInquiry,
                           AM.agencyMemberName,
                           AM.agencyMemberPosition,
                           AM.agencyMemberProfileImg,
                           AM.agencyMemberPhone
                    from Agency as A
                             left join AgencyMember as AM
                                       on AM.agencyIdx = A.agencyIdx
                             left join AgencyRoom as AR
                                       on AR.agencyIdx = A.agencyIdx
                    where AR.roomIdx = :roomIdx
                      and AM.agencyMemberPosition != \"대표공인중개사\"
                      and A.isDeleted = \"N\"
                    limit 1
) as A
                   on A.roomIdx = R.roomIdx";

    //방 이미지 쿼리
    $query2="select COALESCE(roomImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as roomImg from RoomImg where roomIdx=:roomIdx";

    //방 해시태그 쿼리
    $query3="select COALESCE(hashTag,\"null\") as roomImg from RoomHashTag where roomIdx=:roomIdx";

    //query1 실행
    $st = $pdo->prepare($query1);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetch();

    //query2 실행
    $st = $pdo->prepare($query2);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $temp=$st->fetchAll();
    $roomImg=array();
    for($i=0;$i<count($temp);$i++){
        array_push($roomImg,$temp[$i][0]);
    }
    $res["roomImg"]=$roomImg;

    //query3 실행
    $st = $pdo->prepare($query3);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $temp=$st->fetchAll();
    $hashTag=array();
    for($i=0;$i<count($temp);$i++){
        array_push($hashTag,$temp[$i][0]);
    }
    $res["hashTag"]=$hashTag;

    $st = null;
    $pdo = null;

    return $res;
}


function complexDetail($complexIdx,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select COALESCE(UH.heart, \"N\") as heart,
       COALESCE(C.complexName, \"null\") as complexName,
       COALESCE(C.complexAddress, \"null\") as complexAddress,
       COALESCE(C.completionDate, \"null\") as completionDate,
       COALESCE(C.complexNum, \"null\") as complexNum,
       COALESCE(concat(C.householdNum,\"세대\"), \"null\") as householdNum,
       COALESCE(concat(\"가구당 \",C.parkingPerHousehold,\"대\"), \"null\") as parkingPerHousehold,
       COALESCE(C.kindOfHeating, \"null\") as kindOfHeating,
       COALESCE(C.madebyBuilding, \"null\") as madebyBuilding,
       COALESCE(C.complexSize, \"null\") as complexSize,
       COALESCE(C.kindOfHeating, \"null\") as kindOfHeating,
       COALESCE(C.fuel, \"null\") as fuel,
       COALESCE(concat(C.floorAreaRatio,\"%\"), \"null\") as floorAreaRatio,
       COALESCE(concat(C.buildingCoverageRatio,\"%\"), \"null\") as buildingCoverageRatio,
       COALESCE(C.complexDealing, \"-\") as complexDealing,
       COALESCE(C.complexLease, \"-\") as complexLease,
       COALESCE(R.regionDealing, \"-\") as regionDealing,
       COALESCE(R.regionLease, \"-\") as regionLease,
       COALESCE(C.latitude, \"null\") as latitude,
       COALESCE(C.longitude, \"null\") as longitude
from (select * from Complex where complexIdx = :complexIdx) as C
         left join (SELECT userIdx,complexIdx,heart FROM UserHeart
where userIdx = :userIdx
) as UH
                   on UH.complexIdx = C.complexIdx
         left join Region as R
                   on R.dongAddress = C.complexAddress";

    //방 이미지 쿼리
    $query2="select COALESCE(complexImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg from ComplexImg where complexIdx=:complexIdx";

    //query1 실행
    $st = $pdo->prepare($query1);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetch();

    //query2 실행
    $st = $pdo->prepare($query2);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $temp=$st->fetchAll();
    $complexImg=array();
    for($i=0;$i<count($temp);$i++){
        array_push($complexImg,$temp[$i][0]);
    }
    $res["complexImg"]=$complexImg;

    $st = null;
    $pdo = null;

    return $res;
}

function complexSizeInfo($complexIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select
       COALESCE(kindOfArea,\"null\") as kindOfArea,
       COALESCE(roomDesignImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EC%84%A4%EA%B3%84%EB%8F%84%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.jpg?alt=media&token=acfde156-fc0b-4ba3-bf75-f81544c2c6c2\") as roomDesignImg,
       COALESCE(concat(exclusiveArea,\"㎡\"),\"null\") as exclusiveArea,
       COALESCE(concat(contractArea,\"㎡\"),\"null\")as contractArea,
       COALESCE(concat(roomNum,\"개\"),\"null\")as roomNum,
       COALESCE(concat(bathroomNum,\"개\"),\"null\")as bathroomNum,
       COALESCE(concat(householdNum,\"세대\"),\"null\") as householdNum,
       COALESCE(maintenanceCost,\"-\") as maintenanceCost
from ComplexInfo
where complexIdx = :complexIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function surroundingRecommendationComplex($complexIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COM.complexName,
       COALESCE(CI.complexImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg
from (select complexIdx, Max(createdAt) as createdAt
      from ComplexImg
      group by complexIdx) as C
         left join ComplexImg as CI
                   on CI.complexIdx = CI.complexIdx and C.createdAt = CI.createdAt
         left join Complex as COM
                   on COM.complexIdx = CI.complexIdx
where CI.complexIdx != :complexIdx and COM.isDeleted = \"N\" and CI.isDeleted = \"N\"
";

    $st = $pdo->prepare($query);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function ComplexInRoomDetail($roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select R.complexIdx,
       COALESCE(CI.roomDesignImg,\"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EC%84%A4%EA%B3%84%EB%8F%84%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.jpg?alt=media&token=acfde156-fc0b-4ba3-bf75-f81544c2c6c2\") as roomDesignImg,
       COALESCE(concat(CI.exclusiveArea,\"㎡\"),\"null\") as exclusiveArea,
       COALESCE(concat(CI.contractArea,\"㎡\"),\"null\")as contractArea,
       COALESCE(concat(CI.roomNum,\"개\"),\"null\")as roomNum,
       COALESCE(concat(CI.bathroomNum,\"개\"),\"null\")as bathroomNum,
       COALESCE(C.complexType,\"null\")as complexType,
       COALESCE(concat(CI.householdNum,\"세대\"),\"null\") as householdNum
from (select roomIdx, complexIdx
      from RoomInComplex
      where roomIdx = :roomIdx) as R
         left join ComplexInfo as CI
                   on CI.complexIdx = R.complexIdx
         left join Complex as C
                   on R.complexIdx = C.complexIdx
limit 1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0];
}

function agencyDetail($agencyIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(A.agencyComment, \"null\") as agencyComment,
       COALESCE(A.quickInquiry, \"N\") as quickInquiry,
       COALESCE(A.agencyName, \"null\") as agencyName,
       COALESCE(A.agencyBossName, \"null\") as agencyBossName,
       COALESCE(AM.agencyMemberProfileImg, 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(A.mediationNumber, \"null\") as mediationNumber,
       COALESCE(A.companyRegistrationNumber, \"null\") as companyRegistrationNumber,
       COALESCE(A.agencyBossPhone, \"null\") as agencyBossPhone,
       COALESCE(A.agencyAddressDetail, \"null\") as agencyAddress,
       COALESCE(DATE_FORMAT(A.joinDate,\"%Y년 %m월 %d일\"), \"null\") as joinDate,
       COALESCE(concat(A.completedRoom,\"개의 방\"), \"null\") as completedRoom
from Agency as A
left join AgencyMember as AM
on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition = \"대표공인중개사\"
where A.agencyIdx = :agencyIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':agencyIdx',$agencyIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0];
}

function agencyMember($agencyIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(agencyMemberName, \"null\") as agencyMemberName,
       COALESCE(agencyMemberPosition, \"null\") as agencyMemberPosition,
       COALESCE(agencyMemberProfileImg, \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914\") as agencyMemberProfileImg
from AgencyMember where agencyIdx=:agencyIdx;";

    $st = $pdo->prepare($query);
    $st->bindParam(':agencyIdx',$agencyIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function roomOption($roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select I.iconName,I.iconImg from RoomIcon as R
left join Icon as I
on R.iconIdx = I.iconIdx
where R.roomIdx=:roomIdx and I.iconType = \"옵션\";";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function roomSecurity($roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select I.iconName,I.iconImg from RoomIcon as R
left join Icon as I
on R.iconIdx = I.iconIdx
where R.roomIdx=:roomIdx and I.iconType = \"보안/안전시설\";";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function addressRoomNum($address,$roomType)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Room
where roomAddress Like concat('%',:address,'%')
and kindOfRoom regexp :roomType";

    $st = $pdo->prepare($query);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function addressComplexNum($address)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Complex
where complexAddress Like concat('%',:address,'%')";

    $st = $pdo->prepare($query);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function addressAgencyNum($address)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Agency
where agencyAddress like concat('%',:address,'%') and isDeleted='N'";

    $st = $pdo->prepare($query);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function complexRoomNum($complexIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from RoomInComplex
where complexIdx=:complexIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function agencyRoomNum($agencyIdx)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from AgencyRoom
where agencyIdx=:agencyIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':agencyIdx',$agencyIdx,PDO::PARAM_STR);
    $st->execute();
    //    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function rangeComplexNum($roomType,$latitude,$longitude,$scale)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Complex
where
kindOfBuilding regexp :roomType
and isDeleted = 'N'
and latitude >= (:latitude-(:scale/1000))
and latitude <= (:latitude+(:scale/1000))
and longitude >= (:longitude-(:scale/1000))
and longitude <= (:longitude+(:scale/1000))";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',$scale,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function stationComplexNum($roomType,$station)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Complex as C
where
      kindOfBuilding regexp :roomType
  and C.isDeleted = 'N'
  and
      (select Round(6371 * acos(cos(radians((select latitude from Station where stationName = :stationName))) *
                        cos(radians(C.latitude)) * cos(radians(C.longitude) - radians(
                (select longitude from Station where stationName = :stationName))) +
                        sin(radians((select latitude from Station where stationName = :stationName))) *
                        sin(radians(C.latitude))), 0)) <=1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':stationName',$station,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function universityComplexNum($roomType,$university)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Complex as C
where
      kindOfBuilding regexp :roomType
  and C.isDeleted = 'N'
  and
(select Round(6371 * acos(cos(radians((select latitude from University where universityName = :universityName))) *
                        cos(radians(C.latitude)) * cos(radians(C.longitude) - radians(
                (select longitude from University where universityName = :universityName))) +
                        sin(radians((select latitude from University where universityName = :universityName))) *
                        sin(radians(C.latitude))), 0)) <=1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':universityName',$university,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function rangeAgencyNum($latitude,$longitude,$scale)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Agency
where
latitude >= (:latitude-(:scale/1000))
and latitude <= (:latitude+(:scale/1000))
and longitude >= (:longitude-(:scale/1000))
and longitude <= (:longitude+(:scale/1000))
";

    $st = $pdo->prepare($query);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',intval($scale),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}



function stationAgencyNum($station)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Agency as A
where A.isDeleted=\"N\" and
      (select Round(6371 * acos(cos(radians((select latitude from Station where stationName = :stationName))) *
                        cos(radians(A.latitude)) * cos(radians(A.longitude) - radians(
                (select longitude from Station where stationName = :stationName))) +
                        sin(radians((select latitude from Station where stationName = :stationName))) *
                        sin(radians(A.latitude))), 0)) <=1
";

    $st = $pdo->prepare($query);
    $st->bindParam(':stationName',$station,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function universityAgencyNum($university)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Agency as A
where A.isDeleted=\"N\"
and (select Round(6371 * acos(cos(radians((select latitude from University where universityName = :universityName))) *
                        cos(radians(A.latitude)) * cos(radians(A.longitude) - radians(
                (select longitude from University where universityName = :universityName))) +
                        sin(radians((select latitude from University where universityName = :universityName))) *
                        sin(radians(A.latitude))), 0)) <=1
";

    $st = $pdo->prepare($query);
    $st->bindParam(':universityName',$university,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}



function rangeRoomNum($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$latitude,$longitude,$scale)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Room as R
         left join AgencyRoom as AR
                   on R.roomIdx = AR.roomIdx
         left join Agency as A
                   on A.agencyIdx = AR.agencyIdx
         left join UserHeart as UH
                   on R.roomIdx = UH.roomIdx
left join (select agencyIdx, count(agencyIdx) as agencyRoomNum from AgencyRoom
group by agencyIdx) as ARN
on ARN.agencyIdx = A.agencyIdx
where kindOfRoom regexp :roomType and left(maintenanceCost, 1) >= :maintenanceCostMin and left(maintenanceCost, 1) <= :maintenanceCostMax
and left(exclusiveArea, char_length(exclusiveArea)-1) >= :exclusiveAreaMin and left(exclusiveArea, char_length(exclusiveArea)-1) <= :exclusiveAreaMax
and R.isDeleted = 'N'
and R.latitude >= (:latitude-(:scale/1000))
and R.latitude <= (:latitude+(:scale/1000))
and R.longitude >= (:longitude-(:scale/1000))
and R.longitude <= (:longitude+(:scale/1000))";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',intval($scale),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function stationRoomNum($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$station)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Room as R
where
      kindOfRoom regexp :roomType
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) >= :maintenanceCostMin
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) <= :maintenanceCostMax
  and left(exclusiveArea, char_length(exclusiveArea) - 1) >= :exclusiveAreaMin
  and left(exclusiveArea, char_length(exclusiveArea) - 1) <= :exclusiveAreaMax
  and R.isDeleted = 'N'
  and
      (select Round(6371 * acos(cos(radians((select latitude from Station where stationName = :stationName))) *
                        cos(radians(R.latitude)) * cos(radians(R.longitude) - radians(
                (select longitude from Station where stationName = :stationName))) +
                        sin(radians((select latitude from Station where stationName = :stationName))) *
                        sin(radians(R.latitude))), 0)) <=1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':stationName',$station,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}

function universityRoomNum($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$university)
{
    $pdo = pdoSqlConnect();
    $query = "select count(*) from Room as R
where
      kindOfRoom regexp :roomType
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) >= :maintenanceCostMin
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) <= :maintenanceCostMax
  and left(exclusiveArea, char_length(exclusiveArea) - 1) >= :exclusiveAreaMin
  and left(exclusiveArea, char_length(exclusiveArea) - 1) <= :exclusiveAreaMax
  and R.isDeleted = 'N'
  and
(select Round(6371 * acos(cos(radians((select latitude from University where universityName = :universityName))) *
                        cos(radians(R.latitude)) * cos(radians(R.longitude) - radians(
                (select longitude from University where universityName = :universityName))) +
                        sin(radians((select latitude from University where universityName = :universityName))) *
                        sin(radians(R.latitude))), 0)) <=1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':universityName',$university,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_NUM);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res[0][0];
}


function addressComplexList($roomType,$address)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(C.complexIdx, \"null\")     as complexIdx,
       COALESCE(C.complexName, \"null\")     as complexName,
       COALESCE(C.complexAddress, \"null\")     as complexAddress,
       COALESCE(CI.complexImg, \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg,
       COALESCE(RN.roomNum, \"0\")     as roomNum,
       COALESCE(C.kindOfBuilding, \"null\")     as kindOfBuilding,
       COALESCE(concat(C.householdNum,'세대'), \"null\")     as householdNum,
       COALESCE(C.completionDate, \"null\")     as completionDate
from Complex as C
         left join (select COM.complexName,
                           COM.complexAddress,
                           COM.kindOfBuilding,
                           COM.householdNum,
                           COM.completionDate,
                           CI.complexIdx,
                           CI.complexImg as complexImg
                    from (select complexIdx, Max(createdAt) as createdAt
                          from ComplexImg
                          group by complexIdx) as C
                             left join ComplexImg as CI
                                       on CI.complexIdx = CI.complexIdx and C.createdAt = CI.createdAt
                             left join Complex as COM
                                       on COM.complexIdx = CI.complexIdx
) as CI
                   on CI.complexIdx = C.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = C.complexIdx
where C.isDeleted = \"N\"
and C.complexAddress like concat('%',:address,'%')
and C.kindOfBuilding regexp :roomType";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}

function addressAgencyList($address)
{
    $pdo = pdoSqlConnect();
    $query1 = "select A.agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyAddressDetail,'null') as agencyAddress,
       COALESCE(A.agencyComment,'null') as agencyComment,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       A.latitude,
       A.longitude
from Agency as A
         left join (select agencyIdx,
                           COALESCE(agencyMemberProfileImg,
                                    \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914\") as agencyMemberProfileImg
                    from AgencyMember
                    where agencyMemberPosition = \"대표공인중개사\") as AM
                   on AM.agencyIdx = A.agencyIdx
where A.agencyAddress like concat('%',:address,'%') and A.isDeleted='N'";

    $st = $pdo->prepare($query1);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select COALESCE(R.roomIdx, \"null\") as roomIdx,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom, \"null\") as kindOfRoom,
       COALESCE(R.thisFloor, \"null\") as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), \"null\") as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(RI.roomImg, \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as roomImg
from Room as R
left join AgencyRoom as AR
on R.roomIdx = AR.roomIdx
left join (select CI.roomIdx, CI.roomImg as roomImg
from (select roomIdx, Min(roomImgIdx) as roomImgIdx
from RoomImg
group by roomIdx) as C
left join RoomImg as CI
on CI.roomIdx = CI.roomIdx and C.roomImgIdx = CI.roomImgIdx) as RI
on RI.roomIdx=R.roomIdx
where AR.agencyIdx = :agencyIdx and R.isDeleted = \"N\" and R.sold =\"N\"
limit 2";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':agencyIdx',$row['agencyIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_ASSOC);
        $res = $st2->fetchAll();


        if($res){
            $row["roomlist"] = $res;
        }else{
            $row["roomlist"] = 'null';
        }

        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function rangeAgencyList($latitude,$longitude,$scale)
{
    $pdo = pdoSqlConnect();
    $query1 = "select A.agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyAddressDetail,'null') as agencyAddress,
       COALESCE(A.agencyComment,'null') as agencyComment,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       A.latitude,
       A.longitude
from Agency as A
         left join (select agencyIdx,
                           COALESCE(agencyMemberProfileImg,
                                    'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyMemberProfileImg
                    from AgencyMember
                    where agencyMemberPosition = \"대표공인중개사\") as AM
                   on AM.agencyIdx = A.agencyIdx
where A.isDeleted ='N'
and A.latitude >= (:latitude-(:scale/1000))
and A.latitude <= (:latitude+(:scale/1000))
and A.longitude >= (:longitude-(:scale/1000))
and A.longitude <= (:longitude+(:scale/1000))";

    $st = $pdo->prepare($query1);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',intval($scale),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);


    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        $pdo2 = pdoSqlConnect();
        $query2="select COALESCE(R.roomIdx, \"null\") as roomIdx,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom, \"null\") as kindOfRoom,
       COALESCE(R.thisFloor, \"null\") as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), \"null\") as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(RI.roomImg, 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg
from Room as R
left join AgencyRoom as AR
on R.roomIdx = AR.roomIdx
left join (select CI.roomIdx, CI.roomImg as roomImg
from (select roomIdx, Min(roomImgIdx) as roomImgIdx
from RoomImg
group by roomIdx) as C
left join RoomImg as CI
on CI.roomIdx = CI.roomIdx and C.roomImgIdx = CI.roomImgIdx) as RI
on RI.roomIdx=R.roomIdx
where AR.agencyIdx = :agencyIdx and R.isDeleted = \"N\" and R.sold =\"N\"
limit 2";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':agencyIdx',$row['agencyIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_ASSOC);
        $res = $st2->fetchAll();


        if($res){
            $row["roomlist"] = $res;
        }else{
            $row["roomlist"] = 'null';
        }

        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function stationAgencyList($station)
{
    $pdo = pdoSqlConnect();
    $query1 = "select A.agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyAddressDetail,'null') as agencyAddress,
       COALESCE(A.agencyComment,'null') as agencyComment,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       A.latitude,
       A.longitude
from Agency as A
         left join (select agencyIdx,
                           COALESCE(agencyMemberProfileImg,
                                    'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyMemberProfileImg
                    from AgencyMember
                    where agencyMemberPosition = \"대표공인중개사\") as AM
                   on AM.agencyIdx = A.agencyIdx
where A.isDeleted ='N'
and (select Round(6371 * acos(cos(radians((select latitude from Station where stationName = :stationName))) *
                        cos(radians(A.latitude)) * cos(radians(A.longitude) - radians(
                (select longitude from Station where stationName = :stationName))) +
                        sin(radians((select latitude from Station where stationName = :stationName))) *
                        sin(radians(A.latitude))), 0)) <=1";

    $st = $pdo->prepare($query1);
    $st->bindParam(':stationName',$station,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);


    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        $pdo2 = pdoSqlConnect();
        $query2="select COALESCE(R.roomIdx, \"null\") as roomIdx,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom, \"null\") as kindOfRoom,
       COALESCE(R.thisFloor, \"null\") as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), \"null\") as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(RI.roomImg, 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg
from Room as R
left join AgencyRoom as AR
on R.roomIdx = AR.roomIdx
left join (select CI.roomIdx, CI.roomImg as roomImg
from (select roomIdx, Min(roomImgIdx) as roomImgIdx
from RoomImg
group by roomIdx) as C
left join RoomImg as CI
on CI.roomIdx = CI.roomIdx and C.roomImgIdx = CI.roomImgIdx) as RI
on RI.roomIdx=R.roomIdx
where AR.agencyIdx = :agencyIdx and R.isDeleted = \"N\" and R.sold =\"N\"
limit 2";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':agencyIdx',$row['agencyIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_ASSOC);
        $res = $st2->fetchAll();


        if($res){
            $row["roomlist"] = $res;
        }else{
            $row["roomlist"] = 'null';
        }

        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function universityAgencyList($university)
{
    $pdo = pdoSqlConnect();
    $query1 = "select A.agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyAddress,'null') as agencyAddress,
       COALESCE(A.agencyComment,'null') as agencyComment,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       A.latitude,
       A.longitude
from Agency as A
         left join (select agencyIdx,
                           COALESCE(agencyMemberProfileImg,
                                    'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyMemberProfileImg
                    from AgencyMember
                    where agencyMemberPosition = \"대표공인중개사\") as AM
                   on AM.agencyIdx = A.agencyIdx
where A.isDeleted ='N'
and (select Round(6371 * acos(cos(radians((select latitude from University where universityName = :universityName))) *
                        cos(radians(A.latitude)) * cos(radians(A.longitude) - radians(
                (select longitude from University where universityName = :universityName))) +
                        sin(radians((select latitude from University where universityName = :universityName))) *
                        sin(radians(A.latitude))), 0)) <=1";

    $st = $pdo->prepare($query1);
    $st->bindParam(':universityName',$university,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);


    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        $pdo2 = pdoSqlConnect();
        $query2="select COALESCE(R.roomIdx, \"null\") as roomIdx,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom, \"null\") as kindOfRoom,
       COALESCE(R.thisFloor, \"null\") as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), \"null\") as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(RI.roomImg, 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg
from Room as R
left join AgencyRoom as AR
on R.roomIdx = AR.roomIdx
left join (select CI.roomIdx, CI.roomImg as roomImg
from (select roomIdx, Min(roomImgIdx) as roomImgIdx
from RoomImg
group by roomIdx) as C
left join RoomImg as CI
on CI.roomIdx = CI.roomIdx and C.roomImgIdx = CI.roomImgIdx) as RI
on RI.roomIdx=R.roomIdx
where AR.agencyIdx = :agencyIdx and R.isDeleted = \"N\" and R.sold =\"N\"
limit 2";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':agencyIdx',$row['agencyIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_ASSOC);
        $res = $st2->fetchAll();


        if($res){
            $row["roomlist"] = $res;
        }else{
            $row["roomlist"] = 'null';
        }

        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}




function rangeComplexList($roomType,$latitude,$longitude,$scale)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(C.complexIdx, \"null\")     as complexIdx,
       COALESCE(C.complexName, \"null\")     as complexName,
       COALESCE(C.complexAddress, \"null\")     as complexAddress,
       COALESCE(CI.complexImg, \"https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36\") as complexImg,
       COALESCE(RN.roomNum, \"0\")     as roomNum,
       COALESCE(C.kindOfBuilding, \"null\")     as kindOfBuilding,
       COALESCE(concat(C.householdNum,'세대'), \"null\")     as householdNum,
       COALESCE(C.completionDate, \"null\")     as completionDate
from Complex as C
         left join (select COM.complexName,
                           COM.complexAddress,
                           COM.kindOfBuilding,
                           COM.householdNum,
                           COM.completionDate,
                           CI.complexIdx,
                           CI.complexImg as complexImg
                    from (select complexIdx, Max(createdAt) as createdAt
                          from ComplexImg
                          group by complexIdx) as C
                             left join ComplexImg as CI
                                       on CI.complexIdx = CI.complexIdx and C.createdAt = CI.createdAt
                             left join Complex as COM
                                       on COM.complexIdx = CI.complexIdx
) as CI
                   on CI.complexIdx = C.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = C.complexIdx
where C.isDeleted = \"N\"
and C.kindOfBuilding regexp :roomType
and C.latitude >= (:latitude-(:scale/1000))
and C.latitude <= (:latitude+(:scale/1000))
and C.longitude >= (:longitude-(:scale/1000))
and C.longitude <= (:longitude+(:scale/1000))";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',intval($scale),PDO::PARAM_INT);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function stationComplexList($roomType,$station)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(C.complexIdx, 'null')     as complexIdx,
       COALESCE(C.complexName, 'null')     as complexName,
       COALESCE(C.complexAddress, 'null')     as complexAddress,
       COALESCE(CI.complexImg, 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as complexImg,
       COALESCE(RN.roomNum, '0')     as roomNum,
       COALESCE(C.kindOfBuilding, 'null')     as kindOfBuilding,
       COALESCE(concat(C.householdNum,'세대'), 'null')     as householdNum,
       COALESCE(C.completionDate, 'null')     as completionDate
from Complex as C
         left join (select COM.complexName,
                           COM.complexAddress,
                           COM.kindOfBuilding,
                           COM.householdNum,
                           COM.completionDate,
                           CI.complexIdx,
                           CI.complexImg as complexImg
                    from (select complexIdx, Min(complexImgIdx) as complexImgIdx
                          from ComplexImg
                          group by complexIdx) as C
                             left join ComplexImg as CI
                                       on CI.complexIdx = CI.complexIdx and C.complexImgIdx = CI.complexImgIdx
                             left join Complex as COM
                                       on COM.complexIdx = CI.complexIdx
) as CI
                   on CI.complexIdx = C.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = C.complexIdx
where C.isDeleted = 'N'
and C.kindOfBuilding regexp :roomType
and (select Round(6371 * acos(cos(radians((select latitude from Station where stationName = :stationName))) *
                        cos(radians(C.latitude)) * cos(radians(C.longitude) - radians(
                (select longitude from Station where stationName = :stationName))) +
                        sin(radians((select latitude from Station where stationName = :stationName))) *
                        sin(radians(C.latitude))), 0)) <=1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':stationName',$station,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}


function universityComplexList($roomType,$university)
{
    $pdo = pdoSqlConnect();
    $query = "select COALESCE(C.complexIdx, 'null')     as complexIdx,
       COALESCE(C.complexName, 'null')     as complexName,
       COALESCE(C.complexAddress, 'null')     as complexAddress,
       COALESCE(CI.complexImg, 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as complexImg,
       COALESCE(RN.roomNum, '0')     as roomNum,
       COALESCE(C.kindOfBuilding, 'null')     as kindOfBuilding,
       COALESCE(concat(C.householdNum,'세대'), 'null')     as householdNum,
       COALESCE(C.completionDate, 'null')     as completionDate
from Complex as C
         left join (select COM.complexName,
                           COM.complexAddress,
                           COM.kindOfBuilding,
                           COM.householdNum,
                           COM.completionDate,
                           CI.complexIdx,
                           CI.complexImg as complexImg
                    from (select complexIdx, Min(complexImgIdx) as complexImgIdx
                          from ComplexImg
                          group by complexIdx) as C
                             left join ComplexImg as CI
                                       on CI.complexIdx = CI.complexIdx and C.complexImgIdx = CI.complexImgIdx
                             left join Complex as COM
                                       on COM.complexIdx = CI.complexIdx
) as CI
                   on CI.complexIdx = C.complexIdx
         left join (select complexIdx, count(complexIdx) as roomNum
                    from RoomInComplex
                    group by complexIdx) as RN
                   on RN.complexIdx = C.complexIdx
where C.isDeleted = 'N'
and C.kindOfBuilding regexp :roomType
and (select Round(6371 * acos(cos(radians((select latitude from University where universityName = :universityName))) *
                        cos(radians(C.latitude)) * cos(radians(C.longitude) - radians(
                (select longitude from University where universityName = :universityName))) +
                        sin(radians((select latitude from University where universityName = :universityName))) *
                        sin(radians(C.latitude))), 0)) <=1";

    $st = $pdo->prepare($query);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':universityName',$university,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st = null;
    $pdo = null;

    return $res;
}



function addressRoomList($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$address,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select R.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as kindOfRoom,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(R.latitude, 'null') as latitude,
       COALESCE(R.longitude, 'null') as longitude,
       COALESCE(A.agencyIdx,'null') as agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyComment,'null') as agencyName,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(concat(ARN.agencyRoomNum,'개의 방'),'0개의 방') as agencyRoomNum,
       COALESCE(A.quickInquiry,'N') as checkedRoom,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(R.plus,'N') as plus,
       COALESCE(UH.heart,'N') as heart
from Room as R
left join RoomInComplex as RIC
on R.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = R.roomIdx
left join AgencyRoom as AR
on AR.roomIdx = R.roomIdx
left join Agency as A
on AR.agencyIdx = A.agencyIdx
left join AgencyMember as AM
on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition=\"대표공인중개사\"
left join (select agencyIdx, count(agencyIdx) as agencyRoomNum from AgencyRoom
group by agencyIdx) as ARN
on ARN.agencyIdx = AR.agencyIdx
left join (select roomIdx, heart from UserHeart where isDeleted =\"N\" and roomIdx is not null and userIdx = :userIdx) as UH
on UH.roomIdx = R.roomIdx
where kindOfRoom regexp :roomType and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1),'만',1) >= :maintenanceCostMin and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1),'만',1) <= :maintenanceCostMax
and left(exclusiveArea, char_length(exclusiveArea)-1) >= :exclusiveAreaMin and left(exclusiveArea, char_length(exclusiveArea)-1) <= :exclusiveAreaMax
and R.roomAddress Like concat('%',:address,'%') and R.isDeleted = 'N'
order by R.checkedRoom, R.plus desc";

    $st = $pdo->prepare($query1);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $query3="select roomImg from RoomImg
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query3);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $roomImg = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $roomImglist=array();
        for($i=0;$i<count($roomImg);$i++){
            array_push($roomImglist,$roomImg[$i][0]);
        }

        if($roomImglist){
            $row["roomImg"] = $roomImglist;
        }else{
            $row["roomImg"] = 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36';
        }
        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}



function complexRoomList($complexIdx,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select RC.roomIdx,
       C.complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(A.quickInquiry,'N') as quickInquiry,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart
from (select complexIdx, roomIdx
      from RoomInComplex
      where complexIdx = :complexIdx) as RC
         left join Complex as C
                   on RC.complexIdx = C.complexIdx
         left join Room as R
                   on RC.roomIdx = R.roomIdx
         left join AgencyRoom as AR
                   on AR.roomIdx = RC.roomIdx
         left join Agency as A
                   on AR.agencyIdx = A.agencyIdx
         left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx
) as UH
                   on UH.roomIdx = RC.roomIdx
         left join (select RI.roomIdx, R.roomImg
                    from (select min(roomImgIdx) as roomImgIdx, roomIdx
                          from RoomImg
                          group by roomIdx) as RI
                             left join RoomImg as R
                                       on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
                   on RI.roomIdx = RC.roomIdx";

    $st = $pdo->prepare($query1);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function agencyRoomList($agencyIdx,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select AR.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as roomType,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(RI.roomImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36') as roomImg,
       COALESCE(A.quickInquiry,'N') as quickInquiry,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(UH.heart, 'N') as heart
from (select agencyIdx, roomIdx from AgencyRoom where agencyIdx = :agencyIdx) as AR
left join RoomInComplex as RIC
on AR.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join Room as R
on AR.roomIdx = R.roomIdx
left join Agency as A
on AR.agencyIdx = A.agencyIdx
left join (SELECT userIdx, roomIdx, heart
                    FROM UserHeart
                    where userIdx = :userIdx) as UH
on UH.roomIdx = AR.roomIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = AR.roomIdx";

    $st = $pdo->prepare($query1);
    $st->bindParam(':agencyIdx',$agencyIdx,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }
        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function rangeRoomList($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$latitude,$longitude,$scale,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select R.roomIdx,
       COALESCE(C.complexName,'null') as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null') as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null') as lease,
       COALESCE(R.kindOfRoom,'null') as kindOfRoom,
       COALESCE(R.thisFloor,'null') as thisFloor,
       COALESCE(concat(R.exclusiveArea,\"㎡\"), 'null') as exclusiveArea,
       case when R.maintenanceCost=0 then 'null' else concat('관리비 ',R.maintenanceCost,'만') end as maintenanceCost,
       COALESCE(R.roomSummary,'null') as roomSummary,
       COALESCE(R.latitude, 'null') as latitude,
       COALESCE(R.longitude, 'null') as longitude,
       COALESCE(A.agencyIdx,'null') as agencyIdx,
       COALESCE(A.agencyName,'null') as agencyName,
       COALESCE(A.agencyComment,'null') as agencyName,
       COALESCE(AM.agencyMemberProfileImg,'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(concat(ARN.agencyRoomNum,'개의 방'),'0개의 방') as agencyRoomNum,
       COALESCE(A.quickInquiry,'N') as checkedRoom,
       COALESCE(R.checkedRoom,'N') as checkedRoom,
       COALESCE(R.plus,'N') as plus,
       COALESCE(UH.heart,'N') as heart
from Room as R
left join RoomInComplex as RIC
on R.roomIdx = RIC.roomIdx
left join Complex as C
on RIC.complexIdx = C.complexIdx
left join (select RI.roomIdx, R.roomImg
from (select min(roomImgIdx) as roomImgIdx, roomIdx
from RoomImg
group by roomIdx) as RI
left join RoomImg as R
on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
on RI.roomIdx = R.roomIdx
left join AgencyRoom as AR
on AR.roomIdx = R.roomIdx
left join Agency as A
on AR.agencyIdx = A.agencyIdx
left join AgencyMember as AM
on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition=\"대표공인중개사\"
left join (select agencyIdx, count(agencyIdx) as agencyRoomNum from AgencyRoom
group by agencyIdx) as ARN
on ARN.agencyIdx = AR.agencyIdx
left join (select roomIdx, heart from UserHeart where isDeleted =\"N\" and roomIdx is not null and userIdx = :userIdx) as UH
on UH.roomIdx = R.roomIdx
where kindOfRoom regexp :roomType and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1),'만',1) >= :maintenanceCostMin and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1),'만',1) <= :maintenanceCostMax
and left(exclusiveArea, char_length(exclusiveArea)-1) >= :exclusiveAreaMin and left(exclusiveArea, char_length(exclusiveArea)-1) <= :exclusiveAreaMax
and R.isDeleted = 'N'
and R.latitude >= (:latitude-(:scale/1000))
and R.latitude <= (:latitude+(:scale/1000))
and R.longitude >= (:longitude-(:scale/1000))
and R.longitude <= (:longitude+(:scale/1000))
order by R.checkedRoom, R.plus desc";

    $st = $pdo->prepare($query1);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':latitude',$latitude,PDO::PARAM_STR);
    $st->bindParam(':longitude',$longitude,PDO::PARAM_STR);
    $st->bindParam(':scale',intval($scale),PDO::PARAM_INT);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $query3="select roomImg from RoomImg
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query3);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $roomImg = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $roomImglist=array();
        for($i=0;$i<count($roomImg);$i++){
            array_push($roomImglist,$roomImg[$i][0]);
        }

        if($roomImglist){
            $row["roomImg"] = $roomImglist;
        }else{
            $row["roomImg"] = 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36';
        }
        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function stationRoomList($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$station,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select R.roomIdx,
       COALESCE(C.complexName, 'null')                                                                                                                                                                                   as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null')                                                                                                                                              as monthlyRent,
       COALESCE(concat('전세 ',R.lease),'null')                                                                                                                                                                                 as lease,
       COALESCE(R.kindOfRoom, 'null')                                                                                                                                                                                      as kindOfRoom,
       COALESCE(R.thisFloor, 'null')                                                                                                                                                                                       as thisFloor,
       COALESCE(concat(R.exclusiveArea, \"㎡\"), 'null')                                                                                                                                                                      as exclusiveArea,
       case
           when R.maintenanceCost = 0 then 'null'
           else concat('관리비 ', R.maintenanceCost, '만원') end                                                                                                                                                                as maintenanceCost,
       COALESCE(R.roomSummary, 'null')                                                                                                                                                                                     as roomSummary,
       COALESCE(R.latitude, 'null')                                                                                                                                                                                        as latitude,
       COALESCE(R.longitude, 'null')                                                                                                                                                                                       as longitude,
       COALESCE(A.agencyIdx, 'null')                                                                                                                                                                                       as agencyIdx,
       COALESCE(A.agencyName, 'null')                                                                                                                                                                                      as agencyName,
       COALESCE(A.agencyComment, 'null')                                                                                                                                                                                   as agencyName,
       COALESCE(AM.agencyMemberProfileImg,
                'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(concat(ARN.agencyRoomNum, '개의 방'), '0개의 방')                                                                                                                                                                as agencyRoomNum,
       COALESCE(A.quickInquiry, 'N')                                                                                                                                                                                       as checkedRoom,
       COALESCE(R.checkedRoom, 'N')                                                                                                                                                                                        as checkedRoom,
       COALESCE(R.plus, 'N')                                                                                                                                                                                               as plus,
       COALESCE(UH.heart, 'N')                                                                                                                                                                                             as heart
from Room as R
         left join RoomInComplex as RIC
                   on R.roomIdx = RIC.roomIdx
         left join Complex as C
                   on RIC.complexIdx = C.complexIdx
         left join (select RI.roomIdx, R.roomImg
                    from (select min(roomImgIdx) as roomImgIdx, roomIdx
                          from RoomImg
                          group by roomIdx) as RI
                             left join RoomImg as R
                                       on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
                   on RI.roomIdx = R.roomIdx
         left join AgencyRoom as AR
                   on AR.roomIdx = R.roomIdx
         left join Agency as A
                   on AR.agencyIdx = A.agencyIdx
         left join AgencyMember as AM
                   on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition = \"대표공인중개사\"
         left join (select agencyIdx, count(agencyIdx) as agencyRoomNum
                    from AgencyRoom
                    group by agencyIdx) as ARN
                   on ARN.agencyIdx = AR.agencyIdx
         left join (select roomIdx, heart
                    from UserHeart
                    where isDeleted = \"N\" and roomIdx is not null and userIdx = :userIdx) as UH
                   on UH.roomIdx = R.roomIdx
where kindOfRoom regexp :roomType
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) >= :maintenanceCostMin
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) <= :maintenanceCostMax
  and left(exclusiveArea, char_length(exclusiveArea) - 1) >= :exclusiveAreaMin
  and left(exclusiveArea, char_length(exclusiveArea) - 1) <= :exclusiveAreaMax
  and R.isDeleted = 'N'
  and (select Round(6371 * acos(cos(radians((select latitude from Station where stationName = :stationName))) *
                        cos(radians(R.latitude)) * cos(radians(R.longitude) - radians(
                (select longitude from Station where stationName = :stationName))) +
                        sin(radians((select latitude from Station where stationName = :stationName))) *
                        sin(radians(R.latitude))), 0)) <=1
order by R.checkedRoom, R.plus desc";

    $st = $pdo->prepare($query1);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':stationName',$station,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $query3="select roomImg from RoomImg
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query3);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $roomImg = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $roomImglist=array();
        for($i=0;$i<count($roomImg);$i++){
            array_push($roomImglist,$roomImg[$i][0]);
        }

        if($roomImglist){
            $row["roomImg"] = $roomImglist;
        }else{
            $row["roomImg"] = 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36';
        }
        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function UniversityRoomList($roomType,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax,$university,$userIdx)
{
    $pdo = pdoSqlConnect();
    $query1 = "select R.roomIdx,
       COALESCE(C.complexName, 'null')                                                                                                                                                                                   as complexName,
       COALESCE(concat('월세 ',R.deposit,'/',R.monthRent),'null')                                                                                                                                              as monthlyRent,
       COALESCE(R.lease, 'null')                                                                                                                                                                                           as lease,
       COALESCE(R.kindOfRoom, 'null')                                                                                                                                                                                      as kindOfRoom,
       COALESCE(R.thisFloor, 'null')                                                                                                                                                                                       as thisFloor,
       COALESCE(concat(R.exclusiveArea, \"㎡\"), 'null')                                                                                                                                                                      as exclusiveArea,
       case
           when R.maintenanceCost = 0 then 'null'
           else concat('관리비 ', R.maintenanceCost, '만원') end                                                                                                                                                                as maintenanceCost,
       COALESCE(R.roomSummary, 'null')                                                                                                                                                                                     as roomSummary,
       COALESCE(R.latitude, 'null')                                                                                                                                                                                        as latitude,
       COALESCE(R.longitude, 'null')                                                                                                                                                                                       as longitude,
       COALESCE(A.agencyIdx, 'null')                                                                                                                                                                                       as agencyIdx,
       COALESCE(A.agencyName, 'null')                                                                                                                                                                                      as agencyName,
       COALESCE(A.agencyComment, 'null')                                                                                                                                                                                   as agencyName,
       COALESCE(AM.agencyMemberProfileImg,
                'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%ED%94%84%EB%A1%9C%ED%95%84%20%EA%B8%B0%EB%B3%B8%EC%82%AC%EC%A7%84.PNG?alt=media&token=7e94ef45-54cc-4cfa-9b2d-8c091d953914') as agencyBossImg,
       COALESCE(concat(ARN.agencyRoomNum, '개의 방'), '0개의 방')                                                                                                                                                                as agencyRoomNum,
       COALESCE(A.quickInquiry, 'N')                                                                                                                                                                                       as checkedRoom,
       COALESCE(R.checkedRoom, 'N')                                                                                                                                                                                        as checkedRoom,
       COALESCE(R.plus, 'N')                                                                                                                                                                                               as plus,
       COALESCE(UH.heart, 'N')                                                                                                                                                                                             as heart
from Room as R
         left join RoomInComplex as RIC
                   on R.roomIdx = RIC.roomIdx
         left join Complex as C
                   on RIC.complexIdx = C.complexIdx
         left join (select RI.roomIdx, R.roomImg
                    from (select min(roomImgIdx) as roomImgIdx, roomIdx
                          from RoomImg
                          group by roomIdx) as RI
                             left join RoomImg as R
                                       on R.roomImgIdx = RI.roomImgIdx and R.roomIdx = RI.roomIdx) as RI
                   on RI.roomIdx = R.roomIdx
         left join AgencyRoom as AR
                   on AR.roomIdx = R.roomIdx
         left join Agency as A
                   on AR.agencyIdx = A.agencyIdx
         left join AgencyMember as AM
                   on A.agencyBossName = AM.agencyMemberName and AM.agencyMemberPosition = \"대표공인중개사\"
         left join (select agencyIdx, count(agencyIdx) as agencyRoomNum
                    from AgencyRoom
                    group by agencyIdx) as ARN
                   on ARN.agencyIdx = AR.agencyIdx
         left join (select roomIdx, heart
                    from UserHeart
                    where isDeleted = \"N\" and roomIdx is not null and userIdx = :userIdx) as UH
                   on UH.roomIdx = R.roomIdx
where kindOfRoom regexp :roomType
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) >= :maintenanceCostMin
  and SUBSTRING_INDEX(SUBSTRING_INDEX(maintenanceCost, ' ', -1), '만', 1) <= :maintenanceCostMax
  and left(exclusiveArea, char_length(exclusiveArea) - 1) >= :exclusiveAreaMin
  and left(exclusiveArea, char_length(exclusiveArea) - 1) <= :exclusiveAreaMax
  and R.isDeleted = 'N'
  and (select Round(6371 * acos(cos(radians((select latitude from University where universityName = :universityName))) *
                        cos(radians(R.latitude)) * cos(radians(R.longitude) - radians(
                (select longitude from University where universityName = :universityName))) +
                        sin(radians((select latitude from University where universityName = :universityName))) *
                        sin(radians(R.latitude))), 0)) <=1
order by R.checkedRoom, R.plus desc";

    $st = $pdo->prepare($query1);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',ceil($maintenanceCostMin),PDO::PARAM_INT);
    $st->bindParam(':maintenanceCostMax',floor($maintenanceCostMax),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMin',ceil($exclusiveAreaMin),PDO::PARAM_INT);
    $st->bindParam(':exclusiveAreaMax',floor($exclusiveAreaMax),PDO::PARAM_INT);
    $st->bindParam(':universityName',$university,PDO::PARAM_STR);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);

    $result = array();
    //행을 한줄씩 읽음
    while($row = $st -> fetch()) {
        //한줄 읽은 행에 거기에 맞는 해시태그 추가
        $pdo2 = pdoSqlConnect();
        $query2="select hashtag from RoomHashTag
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query2);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $hash = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $hashlist=array();
        for($i=0;$i<count($hash);$i++){
            array_push($hashlist,$hash[$i][0]);
        }

        if($hashlist){
            $row["hashTag"] = $hashlist;
        }else{
            $row["hashTag"] = 'null';
        }

        $query3="select roomImg from RoomImg
        where roomIdx=:roomIdx";
        $st2 = $pdo2->prepare($query3);
        $st2->bindParam(':roomIdx',$row['roomIdx'],PDO::PARAM_STR);
        $st2->execute();
        $st2->setFetchMode(PDO::FETCH_NUM);
        $roomImg = $st2->fetchAll();

        //배열형식으로 되어있어 배열을 품
        $roomImglist=array();
        for($i=0;$i<count($roomImg);$i++){
            array_push($roomImglist,$roomImg[$i][0]);
        }

        if($roomImglist){
            $row["roomImg"] = $roomImglist;
        }else{
            $row["roomImg"] = 'https://firebasestorage.googleapis.com/v0/b/allroom.appspot.com/o/default%2F%EB%B0%A9%20%EA%B8%B0%EB%B3%B8%EC%9D%B4%EB%AF%B8%EC%A7%80.PNG?alt=media&token=ac7a7438-5dde-4666-bccd-6ab0c07d0f36';
        }
        $result[] = $row;

    }

    $st = null;
    $pdo = null;

    return $result;
}


function testPost($name)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO Test (name) VALUES (?);";

    $st = $pdo->prepare($query);
    $st->execute([$name]);

    $st = null;
    $pdo = null;

}

function createRoomLikes($userIdx,$roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "insert into UserHeart (userIdx,roomIdx,heart) values (:userIdx,:roomIdx,'Y');";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}

function createComplexLikes($userIdx,$complexIdx)
{
    $pdo = pdoSqlConnect();
    $query = "insert into UserHeart (userIdx,complexIdx,heart) values (:userIdx,:complexIdx,'Y');";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}



function createCallLog($userIdx,$roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO UserCallLog (userIdx, roomIdx) VALUES (:userIdx,:roomIdx);";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}

function createInquireLog($userIdx,$roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO UserInquiryLog (userIdx, roomIdx) VALUES (:userIdx,:roomIdx);";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}



function insertUserRoomlog($userIdx,$roomIdx)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO UserRoomLog (userIdx, roomIdx) VALUES (:userIdx,:roomIdx);";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}


function insertUserSearchLog($jwtUserIdx,$roomType,$address,$maintenanceCostMin,$maintenanceCostMax,$exclusiveAreaMin,$exclusiveAreaMax)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO UserSearchLog (userIdx, searchLog, roomType,maintenanceCostMin,maintenanceCostMax,exclusiveAreaMin,exclusiveAreaMax) VALUES (:userIdx,:address,:roomType,:maintenanceCostMin,:maintenanceCostMax,:exclusiveAreaMin,:exclusiveAreaMax);";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$jwtUserIdx,PDO::PARAM_STR);
    $st->bindParam(':roomType',$roomType,PDO::PARAM_STR);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMin',$maintenanceCostMin,PDO::PARAM_STR);
    $st->bindParam(':maintenanceCostMax',$maintenanceCostMax,PDO::PARAM_STR);
    $st->bindParam(':exclusiveAreaMin',$exclusiveAreaMin,PDO::PARAM_STR);
    $st->bindParam(':exclusiveAreaMax',$exclusiveAreaMax,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}

function insertComplexNameInUserSearchLog($userIdx,$complexName)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO UserSearchLog (userIdx, searchLog) VALUES (:userIdx,:complexName);";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':complexName',$complexName,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}



function insertUserComplexLog($userIdx,$complexIdx)
{
    $pdo = pdoSqlConnect();
    $query = "INSERT INTO UserComplexLog (userIdx, complexIdx) VALUES (:userIdx,:complexIdx);";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();

    $st = null;
    $pdo = null;
}


function isValidUser($userEmail){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM User WHERE userEmail= ? AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->execute([$userEmail]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidPhone($userPhone){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM User WHERE userPhone= :userPhone AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userPhone',$userPhone,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUserRoomView($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserRoomLog WHERE userIdx=:userIdx AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistAddress($address){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM Region WHERE dongAddress=:address AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':address',$address,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistStation($address){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM Station WHERE stationName=:stationName AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':stationName',$address,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUniversity($address){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM University WHERE universityName=:universityName AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':universityName',$address,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}



function isExistUserRegionView($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserSearchLog WHERE userIdx=:userIdx AND isDeleted = 'N' AND searchLog like '%동') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUserComplexView($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserComplexLog WHERE userIdx=:userIdx AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUserRoomLike($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserHeart WHERE userIdx=:userIdx AND isDeleted = 'N' And heart='Y' And roomIdx is not null) AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUserComplexLike($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserHeart WHERE userIdx=:userIdx AND isDeleted = 'N' And heart='Y' And complexIdx is not null) AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUserRoomInquiry($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserInquiryLog WHERE userIdx=:userIdx AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isExistUserCall($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserCallLog WHERE userIdx=:userIdx AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}



function isRoomLike($userIdx,$roomIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserHeart WHERE userIdx=:userIdx AND roomIdx=:roomIdx AND isDeleted = 'N') AS exist";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isComplexLike($userIdx,$complexIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserHeart WHERE userIdx=:userIdx AND complexIdx=:complexIdx AND isDeleted = 'N') AS exist;;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidUserIdx($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM User WHERE userIdx= ? AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->execute([$userIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}


function isSearchRecently($userIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM UserSearchLog WHERE userIdx=:userIdx AND isDeleted = 'N') AS exist;";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->execute();
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidRoomIdx($roomIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM Room WHERE roomIdx= ?) AS exist;";


    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute([$roomIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidComplexIdx($complexIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM Complex WHERE complexIdx= ?) AS exist;";


    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute([$complexIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidAgencyIdx($agencyIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM Agency WHERE agencyIdx= ?) AS exist;";

    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute([$agencyIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidRoomInComplex($roomIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(SELECT * FROM RoomInComplex WHERE roomIdx= ?) AS exist;";


    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute([$roomIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidRoomOption($roomIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(select I.iconName,I.iconImg from RoomIcon as R
left join Icon as I
on R.iconIdx = I.iconIdx
where R.roomIdx=? and I.iconType = \"옵션\") AS exist;";


    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute([$roomIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

function isValidRoomSecurity($roomIdx){
    $pdo = pdoSqlConnect();
    $query = "SELECT EXISTS(select I.iconName,I.iconImg from RoomIcon as R
left join Icon as I
on R.iconIdx = I.iconIdx
where R.roomIdx=? and I.iconType = \"보안/안전시설\") AS exist;";


    $st = $pdo->prepare($query);
    //    $st->execute([$param,$param]);
    $st->execute([$roomIdx]);
    $st->setFetchMode(PDO::FETCH_ASSOC);
    $res = $st->fetchAll();

    $st=null;$pdo = null;

    return intval($res[0]["exist"]);

}

//UPDATE
function changeRoomLikes($userIdx,$roomIdx){
    $pdo = pdoSqlConnect();
    $query = "update UserHeart
set heart =
    case
        when heart = 'Y' then 'N'
        when heart = 'N' then 'Y'
        end
where userIdx = :userIdx
  and roomIdx = :roomIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':roomIdx',$roomIdx,PDO::PARAM_STR);
    $st->execute();
    $st = null;
    $pdo = null;
}

function changeComplexLikes($userIdx,$complexIdx){
    $pdo = pdoSqlConnect();
    $query = "update UserHeart
set heart =
    case
        when heart = 'Y' then 'N'
        when heart = 'N' then 'Y'
        end
where userIdx = :userIdx
  and complexIdx = :complexIdx";

    $st = $pdo->prepare($query);
    $st->bindParam(':userIdx',$userIdx,PDO::PARAM_STR);
    $st->bindParam(':complexIdx',$complexIdx,PDO::PARAM_STR);
    $st->execute();
    $st = null;
    $pdo = null;
}


// CREATE
//    function addMaintenance($message){
//        $pdo = pdoSqlConnect();
//        $query = "INSERT INTO MAINTENANCE (MESSAGE) VALUES (?);";
//
//        $st = $pdo->prepare($query);
//        $st->execute([$message]);
//
//        $st = null;
//        $pdo = null;
//
//    }




// RETURN BOOLEAN
//    function isRedundantEmail($email){
//        $pdo = pdoSqlConnect();
//        $query = "SELECT EXISTS(SELECT * FROM USER_TB WHERE EMAIL= ?) AS exist;";
//
//
//        $st = $pdo->prepare($query);
//        //    $st->execute([$param,$param]);
//        $st->execute([$email]);
//        $st->setFetchMode(PDO::FETCH_ASSOC);
//        $res = $st->fetchAll();
//
//        $st=null;$pdo = null;
//
//        return intval($res[0]["exist"]);
//
//    }
